<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Db\UserStatsMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Service for user enrichment and display logic
 * Handles Nextcloud user lookups and deleted user display
 */
class UserService {
	public function __construct(
		private IUserManager $userManager,
		private UserStatsMapper $userStatsMapper,
		private RoleMapper $roleMapper,
		private UserRoleMapper $userRoleMapper,
		private IL10N $l10n,
	) {
	}

	/**
	 * Get display name for a user
	 * Returns "Deleted User" if user doesn't exist in Nextcloud
	 */
	public function getUserDisplayName(string $userId): string {
		$user = $this->userManager->get($userId);
		if ($user !== null) {
			return $user->getDisplayName();
		}

		// User doesn't exist in Nextcloud - return generic deleted user name
		return $this->l10n->t('Deleted user');
	}

	/**
	 * Check if a user has been deleted
	 * Checks both Nextcloud user existence and user_stats deleted_at flag
	 */
	public function isUserDeleted(string $userId): bool {
		// First check if user exists in Nextcloud
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return true;
		}

		// Check if marked as deleted in user_stats
		try {
			$stats = $this->userStatsMapper->find($userId);
			return $stats->getDeletedAt() !== null;
		} catch (DoesNotExistException $e) {
			// No stats record, user is not deleted (just hasn't posted yet)
			return false;
		}
	}

	/**
	 * Enrich user data with display name, deleted status, and roles
	 *
	 * @param string $userId
	 * @param array|null $roles Optional pre-fetched roles array
	 * @return array{userId: string, displayName: string, isDeleted: bool, roles: array}
	 */
	public function enrichUserData(string $userId, ?array $roles = null): array {
		$isDeleted = $this->isUserDeleted($userId);
		$displayName = $this->getUserDisplayName($userId);

		// If roles not provided, fetch them
		if ($roles === null) {
			$userRoles = $this->userRoleMapper->findByUserId($userId);
			$roles = [];
			foreach ($userRoles as $userRole) {
				try {
					$role = $this->roleMapper->find($userRole->getRoleId());
					$roles[] = $role->jsonSerialize();
				} catch (\Exception $e) {
					// Role not found, skip
				}
			}
		}

		return [
			'userId' => $userId,
			'displayName' => $displayName,
			'isDeleted' => $isDeleted,
			'roles' => $roles,
		];
	}

	/**
	 * Enrich multiple users at once (for performance)
	 *
	 * @param array<string> $userIds
	 * @param array<string, array> $rolesMap Optional pre-fetched roles map (userId => roles[])
	 * @return array<string, array{userId: string, displayName: string, isDeleted: bool, roles: array}>
	 */
	public function enrichMultipleUsers(array $userIds, ?array $rolesMap = null): array {
		$result = [];

		// If roles not provided, fetch them all at once
		if ($rolesMap === null) {
			$rolesMap = $this->fetchRolesForUsers($userIds);
		}

		foreach ($userIds as $userId) {
			$isDeleted = $this->isUserDeleted($userId);
			$displayName = $this->getUserDisplayName($userId);

			$result[$userId] = [
				'userId' => $userId,
				'displayName' => $displayName,
				'isDeleted' => $isDeleted,
				'roles' => $rolesMap[$userId] ?? [],
			];
		}
		return $result;
	}

	/**
	 * Fetch roles for multiple users efficiently
	 *
	 * @param array<string> $userIds
	 * @return array<string, array> Map of userId => roles[]
	 */
	private function fetchRolesForUsers(array $userIds): array {
		if (empty($userIds)) {
			return [];
		}

		$rolesMap = [];

		// Initialize all user IDs with empty arrays
		foreach ($userIds as $userId) {
			$rolesMap[$userId] = [];
		}

		// Fetch all user roles for these users
		$userRoles = $this->userRoleMapper->findByUserIds($userIds);

		// Group by user ID and fetch role details
		$roleIds = [];
		$userRolesByUser = [];
		foreach ($userRoles as $userRole) {
			$userId = $userRole->getUserId();
			$roleId = $userRole->getRoleId();

			if (!isset($userRolesByUser[$userId])) {
				$userRolesByUser[$userId] = [];
			}
			$userRolesByUser[$userId][] = $roleId;
			$roleIds[$roleId] = true;
		}

		// Fetch all roles at once
		$roles = [];
		$roleEntities = $this->roleMapper->findByIds(array_keys($roleIds));
		foreach ($roleEntities as $role) {
			$roles[$role->getId()] = $role->jsonSerialize();
		}

		// Map roles to users
		foreach ($userRolesByUser as $userId => $userRoleIds) {
			foreach ($userRoleIds as $roleId) {
				if (isset($roles[$roleId])) {
					$rolesMap[$userId][] = $roles[$roleId];
				}
			}
		}

		return $rolesMap;
	}
}
