<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\ForumUserMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

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
	 * Get a single forum user
	 *
	 * @param int $id Forum user ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Forum user returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/users/{id}')]
	public function show(int $id): DataResponse {
		try {
			$user = $this->forumUserMapper->find($id);
			return new DataResponse($user->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching forum user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get forum user by Nextcloud user ID
	 *
	 * @param string $userId Nextcloud user ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Forum user returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/users/by-uid/{userId}')]
	public function byUserId(string $userId): DataResponse {
		try {
			$user = $this->forumUserMapper->findByUserId($userId);
			return new DataResponse($user->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching forum user by user ID: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get current user's forum profile
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Current user's forum profile returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/users/me')]
	public function me(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$forumUser = $this->forumUserMapper->findByUserId($user->getUID());
			return new DataResponse($forumUser->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching current user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch current user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new forum user
	 *
	 * @param string $userId Nextcloud user ID
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Forum user created
	 */
	#[ApiRoute(verb: 'POST', url: '/api/users')]
	public function create(string $userId): DataResponse {
		try {
			$forumUser = new \OCA\Forum\Db\ForumUser();
			$forumUser->setUserId($userId);
			$forumUser->setPostCount(0);
			$forumUser->setCreatedAt(time());
			$forumUser->setUpdatedAt(time());

			$createdUser = $this->forumUserMapper->insert($forumUser);
			return new DataResponse($createdUser->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating forum user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a forum user
	 *
	 * @param int $id Forum user ID
	 * @param int|null $postCount Post count
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Forum user updated
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/users/{id}')]
	public function update(int $id, ?int $postCount = null): DataResponse {
		try {
			$user = $this->forumUserMapper->find($id);

			if ($postCount !== null) {
				$user->setPostCount($postCount);
			}
			$user->setUpdatedAt(time());

			$updatedUser = $this->forumUserMapper->update($user);
			return new DataResponse($updatedUser->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating forum user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a forum user
	 *
	 * @param int $id Forum user ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Forum user deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/users/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$user = $this->forumUserMapper->find($id);
			$this->forumUserMapper->delete($user);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Forum user not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting forum user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete forum user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
