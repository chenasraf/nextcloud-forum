<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

class PermissionService {
	public function __construct(
		private UserRoleMapper $userRoleMapper,
		private RoleMapper $roleMapper,
		private CategoryPermMapper $categoryPermMapper,
		private CategoryMapper $categoryMapper,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check if user has Admin role
	 *
	 * @param string $userId Nextcloud user ID
	 * @return bool True if user has Admin role
	 */
	private function hasAdminRole(string $userId): bool {
		try {
			$roles = $this->roleMapper->findByUserId($userId);

			foreach ($roles as $role) {
				if ($role->getRoleType() === Role::ROLE_TYPE_ADMIN) {
					return true;
				}
			}

			return false;
		} catch (\Exception $e) {
			$this->logger->error("Error checking admin role for user $userId: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if user has Admin or Moderator role
	 *
	 * @param string $userId Nextcloud user ID
	 * @return bool True if user has Admin or Moderator role
	 */
	public function hasAdminOrModeratorRole(string $userId): bool {
		try {
			$roles = $this->roleMapper->findByUserId($userId);

			foreach ($roles as $role) {
				// Check if user has admin or moderator role type
				if ($role->getRoleType() === Role::ROLE_TYPE_ADMIN || $role->getRoleType() === Role::ROLE_TYPE_MODERATOR) {
					return true;
				}
			}

			return false;
		} catch (\Exception $e) {
			$this->logger->error("Error checking admin/moderator role for user $userId: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if user has global permission
	 *
	 * Global permissions are role-based permissions that apply across the entire forum
	 * Examples: canAccessAdminTools, canEditRoles, canEditCategories
	 *
	 * @param string|null $userId Nextcloud user ID (null for guest users)
	 * @param string $permission Permission name in camelCase (e.g., 'canEditRoles')
	 * @return bool True if user has the permission
	 */
	public function hasGlobalPermission(?string $userId, string $permission): bool {
		try {
			// Handle guest users (null userId) - use guest role
			if ($userId === null) {
				try {
					$guestRole = $this->roleMapper->findByRoleType(Role::ROLE_TYPE_GUEST);
					$roles = [$guestRole];
				} catch (DoesNotExistException $e) {
					return false;
				}
			} else {
				$roles = $this->roleMapper->findByUserId($userId);
			}

			if (empty($roles)) {
				return false;
			}

			foreach ($roles as $role) {
				// Check permission using getter method
				// Note: Nextcloud Entity uses magic methods, so we call directly without method_exists check
				$getter = 'get' . ucfirst($permission);

				try {
					if ($role->$getter()) {
						return true;
					}
				} catch (\BadMethodCallException $e) {
					$this->logger->error("Invalid permission method $getter on Role entity: " . $e->getMessage());
					continue;
				}
			}

			return false;
		} catch (\Exception $e) {
			$this->logger->error("Error checking global permission '$permission': " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Check if user has permission on a specific category
	 *
	 * Category permissions are resource-specific and defined per role per category
	 * Examples: canView, canPost, canReply, canModerate
	 *
	 * @param string|null $userId Nextcloud user ID (null for guest users)
	 * @param int $categoryId Category ID
	 * @param string $permission Permission name in camelCase (e.g., 'canView', 'canPost')
	 * @return bool True if user has the permission
	 */
	public function hasCategoryPermission(?string $userId, int $categoryId, string $permission): bool {
		// Admin role has hardcoded full access to all categories (guests can never be admin)
		if ($userId !== null && $this->hasAdminRole($userId)) {
			return true;
		}

		try {
			// Handle guest users (null userId) - use guest role
			if ($userId === null) {
				try {
					$guestRole = $this->roleMapper->findByRoleType(Role::ROLE_TYPE_GUEST);
					$roles = [$guestRole];
				} catch (DoesNotExistException $e) {
					return false;
				}
			} else {
				$roles = $this->roleMapper->findByUserId($userId);
			}

			foreach ($roles as $role) {
				try {
					$perm = $this->categoryPermMapper->findByCategoryAndRole(
						$categoryId,
						$role->getId()
					);

					// Check permission using getter method
					// Note: Nextcloud Entity uses magic methods, so we call directly without method_exists check
					$getter = 'get' . ucfirst($permission);
					try {
						if ($perm->$getter()) {
							return true;
						}
					} catch (\BadMethodCallException $e) {
						$this->logger->error("Invalid permission method $getter on CategoryPerm entity: " . $e->getMessage());
						continue;
					}
				} catch (DoesNotExistException $e) {
					// No permission entry for this category+role combination, continue checking other roles
					continue;
				}
			}

			return false;
		} catch (\Exception $e) {
			$this->logger->error("Error checking category permission '$permission' on category $categoryId: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get category ID from thread ID
	 *
	 * @param int $threadId Thread ID
	 * @return int Category ID
	 * @throws DoesNotExistException If thread doesn't exist
	 */
	public function getCategoryIdFromThread(int $threadId): int {
		$thread = $this->threadMapper->find($threadId);
		return $thread->getCategoryId();
	}

	/**
	 * Get category ID from post ID
	 *
	 * @param int $postId Post ID
	 * @return int Category ID
	 * @throws DoesNotExistException If post or thread doesn't exist
	 */
	public function getCategoryIdFromPost(int $postId): int {
		$post = $this->postMapper->find($postId);
		$thread = $this->threadMapper->find($post->getThreadId());
		return $thread->getCategoryId();
	}

	/**
	 * Check if user is the author of a post
	 *
	 * @param string $userId Nextcloud user ID
	 * @param int $postId Post ID
	 * @return bool True if user is the author
	 */
	public function isPostAuthor(string $userId, int $postId): bool {
		try {
			$post = $this->postMapper->find($postId);
			return $post->getAuthorId() === $userId;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * Check if user is the author of a thread
	 *
	 * @param string $userId Nextcloud user ID
	 * @param int $threadId Thread ID
	 * @return bool True if user is the author
	 */
	public function isThreadAuthor(string $userId, int $threadId): bool {
		try {
			$thread = $this->threadMapper->find($threadId);
			return $thread->getAuthorId() === $userId;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * Get all category IDs that the user has view permission for
	 *
	 * @param string|null $userId Nextcloud user ID (null for guest users)
	 * @return array<int> Array of category IDs
	 */
	public function getAccessibleCategories(?string $userId): array {
		$accessibleCategoryIds = [];

		try {
			// Handle guest users (null userId) - use guest role
			if ($userId === null) {
				try {
					$guestRole = $this->roleMapper->findByRoleType(Role::ROLE_TYPE_GUEST);
					$roles = [$guestRole];
				} catch (DoesNotExistException $e) {
					return [];
				}
			} else {
				// Get all user roles using JOIN
				$roles = $this->roleMapper->findByUserId($userId);

				if (empty($roles)) {
					return [];
				}
			}

			// Get all categories
			$categories = $this->categoryMapper->findAll();

			// Check view permission for each category
			foreach ($categories as $category) {
				if ($this->hasCategoryPermission($userId, $category->getId(), 'canView')) {
					$accessibleCategoryIds[] = $category->getId();
				}
			}

			return $accessibleCategoryIds;
		} catch (\Exception $e) {
			$this->logger->error('Error getting accessible categories: ' . $e->getMessage());
			return [];
		}
	}
}
