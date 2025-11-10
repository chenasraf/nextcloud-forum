<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\UserStatsMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;

/**
 * Service for user enrichment and display logic
 * Handles Nextcloud user lookups and deleted user display
 */
class UserService {
	public function __construct(
		private IUserManager $userManager,
		private UserStatsMapper $userStatsMapper,
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
		return 'Deleted User';
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
	 * Enrich user data with display name and deleted status
	 *
	 * @return array{userId: string, displayName: string, isDeleted: bool}
	 */
	public function enrichUserData(string $userId): array {
		$isDeleted = $this->isUserDeleted($userId);
		$displayName = $this->getUserDisplayName($userId);

		return [
			'userId' => $userId,
			'displayName' => $displayName,
			'isDeleted' => $isDeleted,
		];
	}

	/**
	 * Enrich multiple users at once (for performance)
	 *
	 * @param array<string> $userIds
	 * @return array<string, array{userId: string, displayName: string, isDeleted: bool}>
	 */
	public function enrichMultipleUsers(array $userIds): array {
		$result = [];
		foreach ($userIds as $userId) {
			$result[$userId] = $this->enrichUserData($userId);
		}
		return $result;
	}
}
