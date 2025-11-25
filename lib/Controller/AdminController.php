<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Db\UserStatsMapper;
use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AdminController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private UserStatsMapper $userStatsMapper,
		private UserService $userService,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private CategoryMapper $categoryMapper,
		private UserRoleMapper $userRoleMapper,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private AdminSettingsService $settingsService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get dashboard statistics
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Dashboard stats returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/admin/dashboard')]
	public function dashboard(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Get total counts
			$totalUsers = $this->userStatsMapper->countAll();
			$totalThreads = $this->threadMapper->countAll();
			$totalPosts = $this->postMapper->countAll();
			$totalCategories = $this->categoryMapper->countAll();

			// Get recent activity (last 7 days)
			$weekAgo = time() - (7 * 24 * 60 * 60);
			$recentUsers = $this->userStatsMapper->countSince($weekAgo);
			$recentThreads = $this->threadMapper->countSince($weekAgo);
			$recentPosts = $this->postMapper->countSince($weekAgo);

			// Get top contributors (users with most posts)
			$topContributorsAllTime = $this->userStatsMapper->getTopContributors(5);
			$topContributorsRecent = $this->userStatsMapper->getTopContributorsSince($weekAgo, 5);

			return new DataResponse([
				'totals' => [
					'users' => $totalUsers,
					'threads' => $totalThreads,
					'posts' => $totalPosts,
					'categories' => $totalCategories,
				],
				'recent' => [
					'users' => $recentUsers,
					'threads' => $recentThreads,
					'posts' => $recentPosts,
				],
				'topContributorsAllTime' => $topContributorsAllTime,
				'topContributorsRecent' => $topContributorsRecent,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching dashboard stats: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch dashboard stats'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all forum users with their roles
	 *
	 * @return DataResponse<Http::STATUS_OK, array{users: list<array<string, mixed>>}, array{}>
	 *
	 * 200: Users list returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/admin/users')]
	public function users(): DataResponse {
		try {
			// Get all user stats indexed by userId for quick lookup
			$allStats = $this->userStatsMapper->findAll();
			$statsByUserId = [];
			foreach ($allStats as $stats) {
				$statsByUserId[$stats->getUserId()] = $stats;
			}

			// Collect all user IDs first
			$userIds = [];
			$this->userManager->callForAllUsers(function ($user) use (&$userIds) {
				$userIds[] = $user->getUID();
			});

			// Enrich all users at once for performance (includes roles)
			$enrichedUserData = $this->userService->enrichMultipleUsers($userIds);

			// Build final user list with forum stats
			$enrichedUsers = [];
			foreach ($userIds as $userId) {
				$userInfo = $enrichedUserData[$userId];
				$stats = $statsByUserId[$userId] ?? null;

				$userData = [
					'userId' => $userId,
					'displayName' => $userInfo['displayName'],
					'postCount' => $stats ? $stats->getPostCount() : 0,
					'threadCount' => $stats ? $stats->getThreadCount() : 0,
					'createdAt' => $stats ? $stats->getCreatedAt() : 0,
					'updatedAt' => $stats ? $stats->getUpdatedAt() : 0,
					'deletedAt' => $stats ? $stats->getDeletedAt() : null,
					'isDeleted' => $userInfo['isDeleted'],
					'roles' => $userInfo['roles'],
				];

				$enrichedUsers[] = $userData;
			}

			return new DataResponse(['users' => $enrichedUsers]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching users list: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch users list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get general forum settings
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Settings returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/admin/settings')]
	public function getSettings(): DataResponse {
		try {
			$settings = $this->settingsService->getAllSettings();
			return new DataResponse($settings);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching settings: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update general forum settings
	 *
	 * @param string|null $title Forum title
	 * @param string|null $subtitle Forum subtitle
	 * @param bool|null $allow_guest_access Allow unauthenticated users to view forum content
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Settings updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'PUT', url: '/api/admin/settings')]
	public function updateSettings(?string $title = null, ?string $subtitle = null, ?bool $allow_guest_access = null): DataResponse {
		try {
			// Build settings array with only non-null values
			$settingsToUpdate = [];
			if ($title !== null) {
				$settingsToUpdate[AdminSettingsService::SETTING_TITLE] = $title;
			}
			if ($subtitle !== null) {
				$settingsToUpdate[AdminSettingsService::SETTING_SUBTITLE] = $subtitle;
			}
			if ($allow_guest_access !== null) {
				$settingsToUpdate[AdminSettingsService::SETTING_ALLOW_GUEST_ACCESS] = $allow_guest_access;
			}

			// Update settings and return all settings
			$settings = $this->settingsService->updateSettings($settingsToUpdate);
			return new DataResponse($settings);
		} catch (\Exception $e) {
			$this->logger->error('Error updating settings: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Check if user has admin role
	 */
	private function isUserAdmin(string $userId): bool {
		try {
			$userRoles = $this->userRoleMapper->findByUserId($userId);
			foreach ($userRoles as $userRole) {
				// Role ID 1 is Admin role (from migration)
				if ($userRole->getRoleId() === 1) {
					return true;
				}
			}
			return false;
		} catch (\Exception $e) {
			$this->logger->warning('Error checking admin role: ' . $e->getMessage());
			return false;
		}
	}
}
