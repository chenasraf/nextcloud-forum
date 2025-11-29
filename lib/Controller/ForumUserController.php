<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\ForumUserMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Controller for forum users
 * Note: Forum users are automatically created on first post/thread
 */
class ForumUserController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ForumUserMapper $forumUserMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all forum users
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Forum users returned
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/users')]
	public function index(): DataResponse {
		try {
			$users = $this->forumUserMapper->findAll();
			return new DataResponse(array_map(fn ($u) => $u->jsonSerialize(), $users));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching forum users: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch forum users'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get forum user by Nextcloud user ID
	 * Special case: use "me" to get current forum user
	 *
	 * @param string $userId Nextcloud user ID or "me" for current user
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Forum user returned
	 * 404: Forum user not found (user has not posted yet) or guest user accessing "me"
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/users/{userId}')]
	public function show(string $userId): DataResponse {
		try {
			// Handle "me" as special case for current user
			if ($userId === 'me') {
				$currentUser = $this->userSession->getUser();
				if (!$currentUser) {
					// Guest users have no forum profile - return 404 like a user who hasn't posted yet
					return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
				}
				$userId = $currentUser->getUID();
			}

			$forumUser = $this->forumUserMapper->find($userId);
			return new DataResponse($forumUser->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching forum user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create forum user
	 * Note: This is typically not needed as forum users are auto-created on first post
	 *
	 * @param string $userId Nextcloud user ID
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Forum user created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/users')]
	public function create(string $userId): DataResponse {
		try {
			$forumUser = $this->forumUserMapper->createOrUpdate($userId);
			return new DataResponse($forumUser->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating forum user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
