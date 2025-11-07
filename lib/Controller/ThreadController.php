<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ThreadController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ThreadMapper $threadMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all threads
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Threads returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads')]
	public function index(): DataResponse {
		try {
			$threads = $this->threadMapper->findAll();
			return new DataResponse(array_map(fn ($t) => Thread::enrichThread($t), $threads));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching threads: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch threads'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get threads by category
	 *
	 * @param int $categoryId Category ID
	 * @param int $limit Maximum number of threads to return
	 * @param int $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Threads returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{categoryId}/threads')]
	public function byCategory(int $categoryId, int $limit = 50, int $offset = 0): DataResponse {
		try {
			$threads = $this->threadMapper->findByCategoryId($categoryId, $limit, $offset);
			return new DataResponse(array_map(fn ($t) => Thread::enrichThread($t), $threads));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching threads by category: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch threads'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single thread
	 *
	 * @param int $id Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{id}')]
	public function show(int $id): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);

			// Increment view count
			$thread->setViewCount($thread->getViewCount() + 1);
			/** @var \OCA\Forum\Db\Thread */
			$thread = $this->threadMapper->update($thread);

			return new DataResponse(Thread::enrichThread($thread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a thread by slug
	 *
	 * @param string $slug Thread slug
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads/slug/{slug}')]
	public function bySlug(string $slug): DataResponse {
		try {
			$thread = $this->threadMapper->findBySlug($slug);

			// Increment view count
			$thread->setViewCount($thread->getViewCount() + 1);
			/** @var \OCA\Forum\Db\Thread */
			$thread = $this->threadMapper->update($thread);

			return new DataResponse(Thread::enrichThread($thread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching thread by slug: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new thread
	 *
	 * @param int $categoryId Category ID
	 * @param string $title Thread title
	 * @param string $slug Thread slug
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Thread created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/threads')]
	public function create(int $categoryId, string $title, string $slug): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$thread = new \OCA\Forum\Db\Thread();
			$thread->setCategoryId($categoryId);
			$thread->setAuthorId($user->getUID());
			$thread->setTitle($title);
			$thread->setSlug($slug);
			$thread->setViewCount(0);
			$thread->setPostCount(0);
			$thread->setIsLocked(false);
			$thread->setIsPinned(false);
			$thread->setIsHidden(false);
			$thread->setCreatedAt(time());
			$thread->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Thread */
			$createdThread = $this->threadMapper->insert($thread);
			return new DataResponse($createdThread->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a thread
	 *
	 * @param int $id Thread ID
	 * @param string|null $title Thread title
	 * @param bool|null $isLocked Whether the thread is locked
	 * @param bool|null $isPinned Whether the thread is pinned
	 * @param bool|null $isHidden Whether the thread is hidden
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/threads/{id}')]
	public function update(int $id, ?string $title = null, ?bool $isLocked = null, ?bool $isPinned = null, ?bool $isHidden = null): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);

			if ($title !== null) {
				$thread->setTitle($title);
			}
			if ($isLocked !== null) {
				$thread->setIsLocked($isLocked);
			}
			if ($isPinned !== null) {
				$thread->setIsPinned($isPinned);
			}
			if ($isHidden !== null) {
				$thread->setIsHidden($isHidden);
			}
			$thread->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Thread */
			$updatedThread = $this->threadMapper->update($thread);
			return new DataResponse($updatedThread->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a thread
	 *
	 * @param int $id Thread ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Thread deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/threads/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);
			$this->threadMapper->delete($thread);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
