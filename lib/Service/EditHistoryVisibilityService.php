<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

/**
 * Centralized logic for determining whether a user can view edit history of a post
 */
class EditHistoryVisibilityService {
	public function __construct(
		private AdminSettingsService $adminSettingsService,
		private UserPreferencesService $userPreferencesService,
		private PermissionService $permissionService,
	) {
	}

	/**
	 * Determine whether a viewer can see the edit history of a post
	 *
	 * @param string|null $viewerId The user viewing (null for guests)
	 * @param string $postAuthorId The author of the post
	 * @param int $categoryId The category the post belongs to
	 * @return bool Whether the viewer can see the edit history
	 */
	public function canViewEditHistory(?string $viewerId, string $postAuthorId, int $categoryId): bool {
		// Owners always see their own history
		if ($viewerId !== null && $viewerId === $postAuthorId) {
			return true;
		}

		// Global admins/moderators always see history
		if ($viewerId !== null && $this->permissionService->hasAdminOrModeratorRole($viewerId)) {
			return true;
		}

		// Users with canModerate on the specific category always see history
		if ($viewerId !== null && $this->permissionService->hasCategoryPermission($viewerId, $categoryId, 'canModerate')) {
			return true;
		}

		// Check admin setting: public edit history
		$publicEditHistory = (bool)$this->adminSettingsService->getSetting(
			AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY
		);
		if (!$publicEditHistory) {
			return false;
		}

		// Check user override: post author can hide their edit history
		$allowOverride = (bool)$this->adminSettingsService->getSetting(
			AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE
		);
		if ($allowOverride) {
			$hideEditHistory = (bool)$this->userPreferencesService->getPreference(
				$postAuthorId,
				UserPreferencesService::PREF_HIDE_EDIT_HISTORY
			);
			if ($hideEditHistory) {
				return false;
			}
		}

		return true;
	}
}
