<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\UserRoleMapper;
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
		private ForumUserMapper $forumUserMapper,
		private RoleMapper $roleMapper,
		private UserRoleMapper $userRoleMapper,
		private BBCodeMapper $bbCodeMapper,
		private BBCodeService $bbCodeService,
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
	 * Checks both Nextcloud user existence and forum_users deleted_at flag
	 */
	public function isUserDeleted(string $userId): bool {
		// First check if user exists in Nextcloud
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return true;
		}

		// Check if marked as deleted in forum_users
		try {
			$forumUser = $this->forumUserMapper->find($userId);
			return $forumUser->getDeletedAt() !== null;
		} catch (DoesNotExistException $e) {
			// No forum user record, user is not deleted (just hasn't posted yet)
			return false;
		}
	}

	/**
	 * Enrich user data with display name, deleted status, roles, and signature
	 *
	 * @param string $userId
	 * @param array|null $roles Optional pre-fetched roles array
	 * @param array|null $bbcodes Optional pre-fetched BBCode definitions for parsing signatures
	 * @return array{userId: string, displayName: string, isDeleted: bool, roles: array, signature: ?string, signatureRaw: ?string}
	 */
	public function enrichUserData(string $userId, ?array $roles = null, ?array $bbcodes = null): array {
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

		// Get signature from forum user
		$signatureRaw = null;
		$signature = null;
		try {
			$forumUser = $this->forumUserMapper->find($userId);
			$signatureRaw = $forumUser->getSignature();
			if ($signatureRaw !== null && $signatureRaw !== '') {
				// Parse BBCode in signature
				if ($bbcodes === null) {
					$bbcodes = $this->bbCodeMapper->findAllEnabled();
				}
				$signature = $this->bbCodeService->parse($signatureRaw, $bbcodes);
			}
		} catch (DoesNotExistException $e) {
			// No forum user record, no signature
		}

		return [
			'userId' => $userId,
			'displayName' => $displayName,
			'isDeleted' => $isDeleted,
			'roles' => $roles,
			'signature' => $signature,
			'signatureRaw' => $signatureRaw,
		];
	}

	/**
	 * Enrich multiple users at once (for performance)
	 *
	 * @param array<string> $userIds
	 * @param array<string, array> $rolesMap Optional pre-fetched roles map (userId => roles[])
	 * @param array|null $bbcodes Optional pre-fetched BBCode definitions for parsing signatures
	 * @return array<string, array{userId: string, displayName: string, isDeleted: bool, roles: array, signature: ?string, signatureRaw: ?string}>
	 */
	public function enrichMultipleUsers(array $userIds, ?array $rolesMap = null, ?array $bbcodes = null): array {
		$result = [];

		// If roles not provided, fetch them all at once
		if ($rolesMap === null) {
			$rolesMap = $this->fetchRolesForUsers($userIds);
		}

		// Fetch all forum users at once for signatures
		$signaturesMap = $this->fetchSignaturesForUsers($userIds);

		// Fetch BBCodes once for parsing all signatures (if not provided)
		if ($bbcodes === null) {
			$bbcodes = $this->bbCodeMapper->findAllEnabled();
		}

		foreach ($userIds as $userId) {
			$isDeleted = $this->isUserDeleted($userId);
			$displayName = $this->getUserDisplayName($userId);

			$signatureRaw = $signaturesMap[$userId] ?? null;
			$signature = null;
			if ($signatureRaw !== null && $signatureRaw !== '') {
				$signature = $this->bbCodeService->parse($signatureRaw, $bbcodes);
			}

			$result[$userId] = [
				'userId' => $userId,
				'displayName' => $displayName,
				'isDeleted' => $isDeleted,
				'roles' => $rolesMap[$userId] ?? [],
				'signature' => $signature,
				'signatureRaw' => $signatureRaw,
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

	/**
	 * Search users for autocomplete
	 * Returns users matching the search query in the format expected by NcRichContenteditable
	 *
	 * @param string $search Search query (matches against user ID and display name)
	 * @param int $limit Maximum number of results to return
	 * @return array<array{id: string, label: string, icon: string, source: string}> List of matching users
	 */
	public function searchUsersForAutocomplete(string $search = '', int $limit = 10): array {
		$results = [];
		$search = strtolower(trim($search));

		// Use IUserManager to search users
		// The search method searches both user ID and display name
		$users = $this->userManager->search($search, $limit);

		foreach ($users as $user) {
			$results[] = [
				'id' => $user->getUID(),
				'label' => $user->getDisplayName(),
				'icon' => 'icon-user',
				'source' => 'users',
			];
		}

		return $results;
	}

	/**
	 * Fetch signatures for multiple users efficiently
	 *
	 * @param array<string> $userIds
	 * @return array<string, ?string> Map of userId => signature (raw)
	 */
	private function fetchSignaturesForUsers(array $userIds): array {
		if (empty($userIds)) {
			return [];
		}

		$signaturesMap = [];

		// Initialize all user IDs with null
		foreach ($userIds as $userId) {
			$signaturesMap[$userId] = null;
		}

		// Fetch all forum users for these users
		$forumUsers = $this->forumUserMapper->findByUserIds($userIds);

		// Extract signatures
		foreach ($forumUsers as $forumUser) {
			$signaturesMap[$forumUser->getUserId()] = $forumUser->getSignature();
		}

		return $signaturesMap;
	}
}
