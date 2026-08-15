<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Service\UserPreferencesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class UserPreferencesController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private UserPreferencesService $preferencesService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all user preferences
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: Preferences returned
	 * 401: User not authenticated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/user-preferences')]
	public function index(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$preferences = $this->preferencesService->getAllPreferences($user->getUID());
			return new DataResponse($preferences);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching user preferences: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch preferences'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update user preferences
	 *
	 * Request body should contain key-value pairs of preferences to update
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Preferences updated
	 * 400: Invalid preference key or value
	 * 401: User not authenticated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/user-preferences')]
	public function update(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Filter request body to known preference keys only — strips OCS
			// framework params (_route, format) and derived/read-only fields
			// like `upload_directory_resolved_path` that the frontend may
			// round-trip from a previous GET.
			/** @var array<string, mixed> $preferences */
			$preferences = array_intersect_key(
				$this->request->getParams(),
				array_flip(UserPreferencesService::VALID_KEYS),
			);

			$allPreferences = $this->preferencesService->updatePreferences($user->getUID(), $preferences);
			return new DataResponse($allPreferences);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(
				['error' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST
			);
		} catch (\Exception $e) {
			$this->logger->error('Error updating user preferences: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update preferences'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
