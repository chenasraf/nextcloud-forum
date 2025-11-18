<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get public forum settings (title and subtitle)
	 *
	 * This endpoint is publicly accessible to all users.
	 * For admin-only settings, use AdminController::getSettings()
	 *
	 * @return DataResponse<Http::STATUS_OK, array{title: string, subtitle: string}, array{}>
	 *
	 * 200: Settings retrieved successfully
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function getPublicSettings(): DataResponse {
		try {
			$settings = [
				'title' => $this->config->getSystemValueString('title', $this->l10n->t('Forum')),
				'subtitle' => $this->config->getSystemValueString('subtitle', $this->l10n->t('Welcome to the forum!')),
			];

			return new DataResponse($settings);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching public settings: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
