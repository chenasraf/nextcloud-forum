<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AdminController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ForumUserMapper $forumUserMapper,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private CategoryMapper $categoryMapper,
		private UserRoleMapper $userRoleMapper,
		private IUserSession $userSession,
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
	#[ApiRoute(verb: 'GET', url: '/api/admin/dashboard')]
	public function dashboard(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Check if user has admin role
			if (!$this->isUserAdmin($user->getUID())) {
				return new DataResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
			}

			// Get total counts
			$totalUsers = $this->forumUserMapper->countAll();
			$totalThreads = $this->threadMapper->countAll();
			$totalPosts = $this->postMapper->countAll();
			$totalCategories = $this->categoryMapper->countAll();

			// Get recent activity (last 7 days)
			$weekAgo = time() - (7 * 24 * 60 * 60);
			$recentUsers = $this->forumUserMapper->countSince($weekAgo);
			$recentThreads = $this->threadMapper->countSince($weekAgo);
			$recentPosts = $this->postMapper->countSince($weekAgo);

			// Get top contributors (users with most posts)
			$topContributors = $this->forumUserMapper->getTopContributors(5);

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
				'topContributors' => $topContributors,
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
	#[ApiRoute(verb: 'GET', url: '/api/admin/users')]
	public function users(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Check if user has admin role
			if (!$this->isUserAdmin($user->getUID())) {
				return new DataResponse(['error' => 'Access denied'], Http::STATUS_FORBIDDEN);
			}

			// Get all forum users
			$forumUsers = $this->forumUserMapper->findAll();

			// Enrich with role information
			$enrichedUsers = [];
			foreach ($forumUsers as $forumUser) {
				$userId = $forumUser->getUserId();
				$userData = [
					'id' => $forumUser->getId(),
					'userId' => $userId,
					'postCount' => $forumUser->getPostCount(),
					'createdAt' => $forumUser->getCreatedAt(),
					'updatedAt' => $forumUser->getUpdatedAt(),
					'deletedAt' => $forumUser->getDeletedAt(),
					'isDeleted' => $forumUser->getDeletedAt() !== null,
					'roles' => [],
				];

				// Get user roles
				try {
					$userRoles = $this->userRoleMapper->findByUserId($userId);
					$userData['roles'] = array_map(fn ($ur) => $ur->getRoleId(), $userRoles);
				} catch (\Exception $e) {
					// User has no roles
					$userData['roles'] = [];
				}

				$enrichedUsers[] = $userData;
			}

			return new DataResponse(['users' => $enrichedUsers]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching users list: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch users list'], Http::STATUS_INTERNAL_SERVER_ERROR);
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
