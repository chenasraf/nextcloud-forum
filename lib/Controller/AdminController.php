<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Migration\SeedHelper;
use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\UserRoleService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Migration\IOutput;
use Psr\Log\LoggerInterface;

class AdminController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ForumUserMapper $forumUserMapper,
		private UserService $userService,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private CategoryMapper $categoryMapper,
		private UserRoleMapper $userRoleMapper,
		private RoleMapper $roleMapper,
		private UserRoleService $userRoleService,
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
			$topContributorsAllTime = $this->forumUserMapper->getTopContributors(5);
			$topContributorsRecent = $this->forumUserMapper->getTopContributorsSince($weekAgo, 5);

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
			// Get all forum users indexed by userId for quick lookup
			$allForumUsers = $this->forumUserMapper->findAll();
			$forumUsersByUserId = [];
			foreach ($allForumUsers as $forumUser) {
				$forumUsersByUserId[$forumUser->getUserId()] = $forumUser;
			}

			// Collect all user IDs first
			$userIds = [];
			$this->userManager->callForAllUsers(function ($user) use (&$userIds) {
				$userIds[] = $user->getUID();
			});

			// Enrich all users at once for performance (includes roles)
			$enrichedUserData = $this->userService->enrichMultipleUsers($userIds);

			// Build final user list with forum user data
			$enrichedUsers = [];
			foreach ($userIds as $userId) {
				$userInfo = $enrichedUserData[$userId];
				$forumUser = $forumUsersByUserId[$userId] ?? null;

				$userData = [
					'userId' => $userId,
					'displayName' => $userInfo['displayName'],
					'postCount' => $forumUser ? $forumUser->getPostCount() : 0,
					'threadCount' => $forumUser ? $forumUser->getThreadCount() : 0,
					'createdAt' => $forumUser ? $forumUser->getCreatedAt() : 0,
					'updatedAt' => $forumUser ? $forumUser->getUpdatedAt() : 0,
					'deletedAt' => $forumUser ? $forumUser->getDeletedAt() : null,
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

	/**
	 * Run the repair seeds command to restore default forum data
	 *
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Seeds repaired successfully
	 */
	#[ApiRoute(verb: 'POST', url: '/api/admin/repair-seeds')]
	public function repairSeeds(): DataResponse {
		try {
			$messages = [];
			$migrationOutput = new class($messages) implements IOutput {
				/** @var array<string> */
				private array $messages;

				public function __construct(array &$messages) {
					$this->messages = &$messages;
				}

				public function info($message): void {
					$this->messages[] = $message;
				}

				public function warning($message): void {
					$this->messages[] = '[Warning] ' . $message;
				}

				public function debug($message): void {
					$this->messages[] = '[Debug] ' . $message;
				}

				public function startProgress($max = 0): void {
				}

				public function advance($step = 1, $description = ''): void {
				}

				public function finishProgress(): void {
				}
			};

			SeedHelper::seedAll($migrationOutput, true);

			$this->logger->info('Forum repair seeds completed successfully');
			return new DataResponse([
				'success' => true,
				'message' => implode("\n", $messages),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error running repair seeds: ' . $e->getMessage());
			return new DataResponse([
				'success' => false,
				'message' => 'Failed to repair seeds: ' . $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all available roles
	 *
	 * @return DataResponse<Http::STATUS_OK, array{roles: list<array<string, mixed>>}, array{}>
	 *
	 * 200: Roles list returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/admin/roles')]
	public function getRoles(): DataResponse {
		try {
			$roles = $this->roleMapper->findAll();
			$rolesData = array_map(fn ($role) => [
				'id' => $role->getId(),
				'name' => $role->getName(),
				'roleType' => $role->getRoleType(),
			], $roles);
			return new DataResponse(['roles' => $rolesData]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching roles: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch roles'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Assign a role to a user
	 *
	 * @param string $userId The user ID
	 * @param int $roleId The role ID to assign
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Role assigned successfully
	 */
	#[ApiRoute(verb: 'POST', url: '/api/admin/users/{userId}/roles')]
	public function assignRole(string $userId, int $roleId): DataResponse {
		try {
			// Check if user exists
			$user = $this->userManager->get($userId);
			if ($user === null) {
				return new DataResponse([
					'success' => false,
					'message' => "User '$userId' does not exist.",
				], Http::STATUS_NOT_FOUND);
			}

			// Check if role exists
			try {
				$role = $this->roleMapper->find($roleId);
			} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
				return new DataResponse([
					'success' => false,
					'message' => "Role with ID '$roleId' does not exist.",
				], Http::STATUS_NOT_FOUND);
			}

			// Check if user already has this role
			if ($this->userRoleService->hasRole($userId, $roleId)) {
				return new DataResponse([
					'success' => true,
					'message' => "User '$userId' already has the role '{$role->getName()}'.",
				]);
			}

			// Assign the role
			$this->userRoleService->assignRole($userId, $roleId, skipIfExists: false);
			$this->logger->info("Assigned role '{$role->getName()}' to user '$userId'");

			return new DataResponse([
				'success' => true,
				'message' => "Successfully assigned role '{$role->getName()}' to user '$userId'.",
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error assigning role: ' . $e->getMessage());
			return new DataResponse([
				'success' => false,
				'message' => 'Failed to assign role: ' . $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Remove a role from a user
	 *
	 * @param string $userId The user ID
	 * @param int $roleId The role ID to remove
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>
	 *
	 * 200: Role removed successfully
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'DELETE', url: '/api/admin/users/{userId}/roles/{roleId}')]
	public function removeRole(string $userId, int $roleId): DataResponse {
		try {
			// Check if user exists
			$user = $this->userManager->get($userId);
			if ($user === null) {
				return new DataResponse([
					'success' => false,
					'message' => "User '$userId' does not exist.",
				], Http::STATUS_NOT_FOUND);
			}

			// Check if role exists
			try {
				$role = $this->roleMapper->find($roleId);
			} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
				return new DataResponse([
					'success' => false,
					'message' => "Role with ID '$roleId' does not exist.",
				], Http::STATUS_NOT_FOUND);
			}

			// Remove the role
			$removed = $this->userRoleService->removeRole($userId, $roleId);
			if (!$removed) {
				return new DataResponse([
					'success' => true,
					'message' => "User '$userId' does not have the role '{$role->getName()}'.",
				]);
			}

			$this->logger->info("Removed role '{$role->getName()}' from user '$userId'");
			return new DataResponse([
				'success' => true,
				'message' => "Successfully removed role '{$role->getName()}' from user '$userId'.",
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error removing role: ' . $e->getMessage());
			return new DataResponse([
				'success' => false,
				'message' => 'Failed to remove role: ' . $e->getMessage(),
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
