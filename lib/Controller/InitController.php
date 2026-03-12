<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Migration\SeedHelper;
use OCA\Forum\Service\AdminSettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class InitController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private AdminSettingsService $settingsService,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get Nextcloud admin users for initialization
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: string, displayName: string}>, array{}>
	 *
	 * 200: Admin users retrieved successfully
	 */
	#[ApiRoute(verb: 'GET', url: '/api/init/admin-users')]
	public function getAdminUsers(): DataResponse {
		$users = [];

		$this->userManager->callForAllUsers(function ($user) use (&$users) {
			$userId = $user->getUID();
			if ($this->groupManager->isAdmin($userId)) {
				$users[] = [
					'id' => $userId,
					'displayName' => $user->getDisplayName(),
				];
			}
		});

		return new DataResponse($users);
	}

	/**
	 * Run forum initialization with selected admin users
	 *
	 * @param list<string> $adminUserIds List of user IDs to assign the admin role
	 * @return DataResponse<Http::STATUS_OK, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_CONFLICT, array{message: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Initialization completed successfully
	 * 400: Invalid request
	 * 409: Already initialized
	 * 500: Initialization failed
	 */
	#[ApiRoute(verb: 'POST', url: '/api/init/initialize')]
	public function initialize(array $adminUserIds = []): DataResponse {
		// Check if already initialized
		if ($this->settingsService->getSetting(AdminSettingsService::SETTING_IS_INITIALIZED)) {
			return new DataResponse(
				['message' => 'Forum is already initialized'],
				Http::STATUS_CONFLICT,
			);
		}

		if (empty($adminUserIds)) {
			return new DataResponse(
				['message' => 'At least one admin account must be selected'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		// Validate all user IDs exist
		foreach ($adminUserIds as $userId) {
			if (!is_string($userId) || $this->userManager->get($userId) === null) {
				return new DataResponse(
					['message' => "Account '$userId' does not exist"],
					Http::STATUS_BAD_REQUEST,
				);
			}
		}

		try {
			SeedHelper::seedAll(null, true, $adminUserIds);
			return new DataResponse(['message' => 'Forum initialized successfully']);
		} catch (\Exception $e) {
			$this->logger->error('Forum initialization failed', ['exception' => $e->getMessage()]);
			return new DataResponse(
				['message' => 'Initialization failed: ' . $e->getMessage()],
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
	}
}
