<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\UserStatsMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Controller for user statistics
 * Note: User stats are automatically created on first post/thread
 */
class ForumUserController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private UserStatsMapper $userStatsMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all user statistics
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: User statistics returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/users')]
	public function index(): DataResponse {
		try {
			$users = $this->userStatsMapper->findAll();
			return new DataResponse(array_map(fn ($u) => $u->jsonSerialize(), $users));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching user stats: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch user stats'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get user statistics by Nextcloud user ID
	 * Special case: use "me" to get current user stats
	 *
	 * @param string $userId Nextcloud user ID or "me" for current user
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: User stats returned
	 * 401: User not authenticated (when using "me")
	 * 404: User has no stats (hasn't posted yet)
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/users/{userId}')]
	public function show(string $userId): DataResponse {
		try {
			// Handle "me" as special case for current user
			if ($userId === 'me') {
				$currentUser = $this->userSession->getUser();
				if (!$currentUser) {
					return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
				}
				$userId = $currentUser->getUID();
			}

			$stats = $this->userStatsMapper->find($userId);
			return new DataResponse($stats->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'User stats not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching user stats: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch user stats'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create user stats
	 * Note: This is typically not needed as stats are auto-created on first post
	 *
	 * @param string $userId Nextcloud user ID
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: User stats created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/users')]
	public function create(string $userId): DataResponse {
		try {
			$stats = $this->userStatsMapper->createOrUpdate($userId);
			return new DataResponse($stats->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating user stats: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create user stats'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
