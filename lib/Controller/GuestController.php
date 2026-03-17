<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Service\GuestService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class GuestController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private GuestService $guestService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get or create a guest identity
	 *
	 * @param string $guestToken 32-character hex token
	 * @return DataResponse<Http::STATUS_OK, array{displayName: string, guestToken: string, isGuest: true}, array{}>
	 *
	 * 200: Guest identity returned
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/guest/me')]
	public function me(string $guestToken): DataResponse {
		try {
			$session = $this->guestService->getOrCreateSession($guestToken);

			return new DataResponse([
				'displayName' => $session->getDisplayName(),
				'guestToken' => $session->getSessionToken(),
				'isGuest' => true,
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->error('Error resolving guest identity: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to resolve guest identity'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
