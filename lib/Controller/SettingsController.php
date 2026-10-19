<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\ThreadSummaryService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SettingsController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private AdminSettingsService $settingsService,
		private ThreadSummaryService $threadSummaryService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get public forum settings (title, subtitle, and guest access)
	 *
	 * This endpoint is publicly accessible to all users.
	 * For admin-only settings, use AdminController::getSettings()
	 *
	 * @return DataResponse<Http::STATUS_OK, array{title: string, subtitle: string, allow_guest_access: bool}, array{}>
	 *
	 * 200: Settings retrieved successfully
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function getPublicSettings(): DataResponse {
		try {
			$settings = $this->settingsService->getAllSettings();
			// Summaries run as the requesting account, so guests never get them
			$userId = $this->userSession->getUser()?->getUID();
			$settings['thread_summaries_available'] = $userId !== null && $this->threadSummaryService->isAvailable($userId);
			return new DataResponse($settings);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching public settings: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
