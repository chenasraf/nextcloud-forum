<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\PostMapper;
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
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check if user has global permission
	 *
	 * Global permissions are role-based permissions that apply across the entire forum
	 * Examples: canAccessAdminTools, canEditRoles, canEditCategories
	 *
	 * @param string $userId Nextcloud user ID
	 * @param string $permission Permission name in camelCase (e.g., 'canEditRoles')
	 * @return bool True if user has the permission
	 */
	public function hasGlobalPermission(string $userId, string $permission): bool {
		try {
			$userRoles = $this->userRoleMapper->findByUserId($userId);

			$this->logger->info("Checking global permission '$permission' for user $userId. Found " . count($userRoles) . ' role assignments.');

			if (empty($userRoles)) {
				$this->logger->warning("User $userId has no role assignments in forum_user_roles table");
				return false;
			}

			foreach ($userRoles as $userRole) {
				try {
					$role = $this->roleMapper->find($userRole->getRoleId());

					// Check permission using getter method
					// Note: Nextcloud Entity uses magic methods, so we call directly without method_exists check
					$getter = 'get' . ucfirst($permission);
					$this->logger->debug("Checking role '{$role->getName()}' (ID: {$role->getId()}) with getter method: $getter");

					try {
						$hasPermission = $role->$getter();
						$this->logger->debug("Role '{$role->getName()}' permission value: " . ($hasPermission ? 'true' : 'false'));

						if ($hasPermission) {
							$this->logger->info("User $userId has global permission '$permission' via role {$role->getName()}");
							return true;
						}
					} catch (\BadMethodCallException $e) {
						$this->logger->error("Invalid permission method $getter on Role entity: " . $e->getMessage());
						continue;
					}
				} catch (DoesNotExistException $e) {
					// Role doesn't exist anymore, skip
					$this->logger->warning("Role ID {$userRole->getRoleId()} assigned to user $userId does not exist");
					continue;
				}
			}

			$this->logger->warning("User $userId lacks global permission '$permission' - checked all roles, none had permission");
			return false;
		} catch (\Exception $e) {
			$this->logger->error("Error checking global permission '$permission' for user $userId: " . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString(),
			]);
			return false;
		}
	}

	/**
	 * Check if user has permission on a specific category
	 *
	 * Category permissions are resource-specific and defined per role per category
	 * Examples: canView, canPost, canReply, canModerate
	 *
	 * @param string $userId Nextcloud user ID
	 * @param int $categoryId Category ID
	 * @param string $permission Permission name in camelCase (e.g., 'canView', 'canPost')
	 * @return bool True if user has the permission
	 */
	public function hasCategoryPermission(string $userId, int $categoryId, string $permission): bool {
		try {
			$userRoles = $this->userRoleMapper->findByUserId($userId);

			foreach ($userRoles as $userRole) {
				try {
					$perm = $this->categoryPermMapper->findByCategoryAndRole(
						$categoryId,
						$userRole->getRoleId()
					);

					// Check permission using getter method
					// Note: Nextcloud Entity uses magic methods, so we call directly without method_exists check
					$getter = 'get' . ucfirst($permission);
					try {
						if ($perm->$getter()) {
							$this->logger->debug("User $userId has category permission '$permission' on category $categoryId");
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

			$this->logger->debug("User $userId lacks category permission '$permission' on category $categoryId");
			return false;
		} catch (\Exception $e) {
			$this->logger->error("Error checking category permission '$permission' for user $userId on category $categoryId: " . $e->getMessage());
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
}
