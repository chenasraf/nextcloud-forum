<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\UserRole;
use OCA\Forum\Db\UserRoleMapper;
use Psr\Log\LoggerInterface;

/**
 * Service for managing user role assignments
 */
class UserRoleService {
	/** @var int Admin role ID */
	public const ROLE_ADMIN = 1;

	/** @var int Moderator role ID */
	public const ROLE_MODERATOR = 2;

	/** @var int User role ID */
	public const ROLE_USER = 3;

	public function __construct(
		private UserRoleMapper $userRoleMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Assign a role to a user
	 *
	 * @param string $userId The user ID
	 * @param int $roleId The role ID to assign
	 * @param bool $skipIfExists If true, silently skip if user already has the role. If false, log a warning.
	 * @return UserRole|null The created UserRole, or null if already exists and skipIfExists is true
	 */
	public function assignRole(string $userId, int $roleId, bool $skipIfExists = true): ?UserRole {
		// Check if user already has this role
		if ($this->hasRole($userId, $roleId)) {
			if ($skipIfExists) {
				return null;
			}

			$this->logger->warning('User {userId} already has role {roleId}', [
				'userId' => $userId,
				'roleId' => $roleId,
			]);
			return null;
		}

		// Create and insert the new user role
		$userRole = new UserRole();
		$userRole->setUserId($userId);
		$userRole->setRoleId($roleId);
		$userRole->setCreatedAt(time());

		try {
			$createdRole = $this->userRoleMapper->insert($userRole);
			$this->logger->info('Assigned role {roleId} to user {userId}', [
				'userId' => $userId,
				'roleId' => $roleId,
			]);
			return $createdRole;
		} catch (\Exception $ex) {
			$this->logger->error('Failed to assign role {roleId} to user {userId}: {error}', [
				'userId' => $userId,
				'roleId' => $roleId,
				'error' => $ex->getMessage(),
			]);
			throw $ex;
		}
	}

	/**
	 * Check if a user has a specific role
	 *
	 * @param string $userId The user ID
	 * @param int $roleId The role ID to check
	 * @return bool True if user has the role, false otherwise
	 */
	public function hasRole(string $userId, int $roleId): bool {
		$userRoles = $this->userRoleMapper->findByUserId($userId);
		foreach ($userRoles as $userRole) {
			if ($userRole->getRoleId() === $roleId) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove a role from a user
	 *
	 * @param string $userId The user ID
	 * @param int $roleId The role ID to remove
	 * @return bool True if role was removed, false if user didn't have the role
	 */
	public function removeRole(string $userId, int $roleId): bool {
		$userRoles = $this->userRoleMapper->findByUserId($userId);
		foreach ($userRoles as $userRole) {
			if ($userRole->getRoleId() === $roleId) {
				try {
					$this->userRoleMapper->delete($userRole);
					$this->logger->info('Removed role {roleId} from user {userId}', [
						'userId' => $userId,
						'roleId' => $roleId,
					]);
					return true;
				} catch (\Exception $ex) {
					$this->logger->error('Failed to remove role {roleId} from user {userId}: {error}', [
						'userId' => $userId,
						'roleId' => $roleId,
						'error' => $ex->getMessage(),
					]);
					throw $ex;
				}
			}
		}

		$this->logger->debug('User {userId} does not have role {roleId}, nothing to remove', [
			'userId' => $userId,
			'roleId' => $roleId,
		]);
		return false;
	}

	/**
	 * Get all role IDs for a user
	 *
	 * @param string $userId The user ID
	 * @return array<int> Array of role IDs
	 */
	public function getUserRoleIds(string $userId): array {
		$userRoles = $this->userRoleMapper->findByUserId($userId);
		return array_map(fn ($userRole) => $userRole->getRoleId(), $userRoles);
	}
}
