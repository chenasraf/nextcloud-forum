<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\ReadMarkerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ReadMarkerController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ReadMarkerMapper $readMarkerMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get read markers for current user
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Read markers returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/read-markers')]
	public function index(): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$markers = $this->readMarkerMapper->findByUserId($user->getUID());
			return new DataResponse(array_map(fn ($m) => $m->jsonSerialize(), $markers));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching read markers: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch read markers'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get read marker for a specific thread
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Read marker returned
	 */
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/read-marker')]
	public function show(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$marker = $this->readMarkerMapper->findByUserAndThread($user->getUID(), $threadId);
			return new DataResponse($marker->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Read marker not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching read marker: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch read marker'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Mark a thread as read
	 *
	 * @param int $threadId Thread ID
	 * @param int $lastReadPostId Last read post ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread marked as read
	 */
	#[ApiRoute(verb: 'POST', url: '/api/read-markers')]
	public function create(int $threadId, int $lastReadPostId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Check if marker already exists, if so update it
			try {
				$marker = $this->readMarkerMapper->findByUserAndThread($user->getUID(), $threadId);
				$marker->setLastReadPostId($lastReadPostId);
				$marker->setReadAt(time());
				$updatedMarker = $this->readMarkerMapper->update($marker);
				return new DataResponse($updatedMarker->jsonSerialize());
			} catch (DoesNotExistException $e) {
				// Create new marker
				$marker = new \OCA\Forum\Db\ReadMarker();
				$marker->setUserId($user->getUID());
				$marker->setThreadId($threadId);
				$marker->setLastReadPostId($lastReadPostId);
				$marker->setReadAt(time());

				$createdMarker = $this->readMarkerMapper->insert($marker);
				return new DataResponse($createdMarker->jsonSerialize());
			}
		} catch (\Exception $e) {
			$this->logger->error('Error marking thread as read: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to mark thread as read'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a read marker
	 *
	 * @param int $id Read marker ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Read marker deleted
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/read-markers/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$marker = $this->readMarkerMapper->find($id);
			$this->readMarkerMapper->delete($marker);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Read marker not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting read marker: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete read marker'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
