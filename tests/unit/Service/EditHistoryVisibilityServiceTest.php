<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\EditHistoryVisibilityService;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\UserPreferencesService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EditHistoryVisibilityServiceTest extends TestCase {
	private EditHistoryVisibilityService $service;
	/** @var AdminSettingsService&MockObject */
	private AdminSettingsService $adminSettingsService;
	/** @var UserPreferencesService&MockObject */
	private UserPreferencesService $userPreferencesService;
	/** @var PermissionService&MockObject */
	private PermissionService $permissionService;

	private const CATEGORY_ID = 10;

	protected function setUp(): void {
		$this->adminSettingsService = $this->createMock(AdminSettingsService::class);
		$this->userPreferencesService = $this->createMock(UserPreferencesService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->service = new EditHistoryVisibilityService(
			$this->adminSettingsService,
			$this->userPreferencesService,
			$this->permissionService,
		);
	}

	public function testOwnerCanAlwaysSeeOwnHistory(): void {
		// No admin settings or permissions needed — owner always sees own history
		$this->assertTrue(
			$this->service->canViewEditHistory('user1', 'user1', self::CATEGORY_ID)
		);
	}

	public function testModeratorCanAlwaysSeeHistory(): void {
		$this->permissionService->method('hasCategoryPermission')
			->with('mod1', self::CATEGORY_ID, 'canModerate')
			->willReturn(true);
		$this->permissionService->method('hasAdminOrModeratorRole')
			->willReturn(false);

		$this->assertTrue(
			$this->service->canViewEditHistory('mod1', 'user1', self::CATEGORY_ID)
		);
	}

	public function testAdminRoleCanAlwaysSeeHistory(): void {
		$this->permissionService->method('hasCategoryPermission')
			->willReturn(false);
		$this->permissionService->method('hasAdminOrModeratorRole')
			->with('admin1')
			->willReturn(true);

		$this->assertTrue(
			$this->service->canViewEditHistory('admin1', 'user1', self::CATEGORY_ID)
		);
	}

	public function testPublicEditHistoryOffBlocksOtherUsers(): void {
		$this->permissionService->method('hasCategoryPermission')->willReturn(false);
		$this->permissionService->method('hasAdminOrModeratorRole')->willReturn(false);

		$this->adminSettingsService->method('getSetting')
			->with(AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY)
			->willReturn(false);

		$this->assertFalse(
			$this->service->canViewEditHistory('viewer1', 'author1', self::CATEGORY_ID)
		);
	}

	public function testPublicEditHistoryOnAllowsOtherUsers(): void {
		$this->permissionService->method('hasCategoryPermission')->willReturn(false);
		$this->permissionService->method('hasAdminOrModeratorRole')->willReturn(false);

		$this->adminSettingsService->method('getSetting')
			->willReturnMap([
				[AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY, true],
				[AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE, false],
			]);

		$this->assertTrue(
			$this->service->canViewEditHistory('viewer1', 'author1', self::CATEGORY_ID)
		);
	}

	public function testUserOverrideHidesHistoryFromOthers(): void {
		$this->permissionService->method('hasCategoryPermission')->willReturn(false);
		$this->permissionService->method('hasAdminOrModeratorRole')->willReturn(false);

		$this->adminSettingsService->method('getSetting')
			->willReturnMap([
				[AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY, true],
				[AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE, true],
			]);

		$this->userPreferencesService->method('getPreference')
			->with('author1', UserPreferencesService::PREF_HIDE_EDIT_HISTORY)
			->willReturn(true);

		$this->assertFalse(
			$this->service->canViewEditHistory('viewer1', 'author1', self::CATEGORY_ID)
		);
	}

	public function testUserOverrideDoesNotAffectOwner(): void {
		// Owner always sees own history regardless of override setting
		$this->assertTrue(
			$this->service->canViewEditHistory('author1', 'author1', self::CATEGORY_ID)
		);
	}

	public function testUserOverrideDoesNotAffectModerator(): void {
		$this->permissionService->method('hasCategoryPermission')
			->with('mod1', self::CATEGORY_ID, 'canModerate')
			->willReturn(true);
		$this->permissionService->method('hasAdminOrModeratorRole')
			->willReturn(false);

		// Moderator can see history even when author has hidden it
		$this->assertTrue(
			$this->service->canViewEditHistory('mod1', 'author1', self::CATEGORY_ID)
		);
	}

	public function testGuestCannotSeeHistoryWhenPublicOff(): void {
		$this->adminSettingsService->method('getSetting')
			->with(AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY)
			->willReturn(false);

		$this->assertFalse(
			$this->service->canViewEditHistory(null, 'author1', self::CATEGORY_ID)
		);
	}

	public function testGuestCanSeeHistoryWhenPublicOn(): void {
		$this->adminSettingsService->method('getSetting')
			->willReturnMap([
				[AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY, true],
				[AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE, false],
			]);

		$this->assertTrue(
			$this->service->canViewEditHistory(null, 'author1', self::CATEGORY_ID)
		);
	}

	public function testUserOverrideDisabledDoesNotHideHistory(): void {
		$this->permissionService->method('hasCategoryPermission')->willReturn(false);
		$this->permissionService->method('hasAdminOrModeratorRole')->willReturn(false);

		$this->adminSettingsService->method('getSetting')
			->willReturnMap([
				[AdminSettingsService::SETTING_PUBLIC_EDIT_HISTORY, true],
				[AdminSettingsService::SETTING_ALLOW_EDIT_HISTORY_USER_OVERRIDE, false],
			]);

		// Even if user has hide_edit_history pref set, when override is disabled, history is visible
		$this->assertTrue(
			$this->service->canViewEditHistory('viewer1', 'author1', self::CATEGORY_ID)
		);
	}
}
