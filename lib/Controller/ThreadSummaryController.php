<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\ThreadSummaryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\TaskProcessing\Exception\NotFoundException;
use Psr\Log\LoggerInterface;

class ThreadSummaryController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ThreadMapper $threadMapper,
		private ThreadSummaryService $threadSummaryService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Request an AI-generated summary of a thread
	 *
	 * The summary is generated in the background by the summary provider
	 * configured in Nextcloud; poll the returned task until it finishes.
	 *
	 * @param int $threadId Thread ID
	 * @param int $afterPostId Summarize only replies newer than this post (0 for the whole thread)
	 * @return DataResponse<Http::STATUS_OK, array{taskId: int|null, status: string, summary: string|null}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_SERVICE_UNAVAILABLE|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Summary task scheduled, or an earlier task for the same content returned
	 * 400: Nothing to summarize
	 * 404: Thread not found
	 * 503: Summaries are disabled or no summary provider is available
	 */
	#[NoAdminRequired]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'POST', url: '/api/threads/{threadId}/summary')]
	public function create(int $threadId, int $afterPostId = 0): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->threadSummaryService->isAvailable($userId)) {
			return new DataResponse(['error' => 'Thread summaries are not available'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		try {
			$thread = $this->threadMapper->find($threadId);
			$task = $this->threadSummaryService->schedule($userId, $thread, max(0, $afterPostId));
			if ($task === null) {
				return new DataResponse(['error' => 'Nothing to summarize'], Http::STATUS_BAD_REQUEST);
			}
			return new DataResponse($this->threadSummaryService->serializeTask($task));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error scheduling thread summary: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['error' => 'Failed to summarize thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get the state of a thread summary requested by the current user
	 *
	 * @param int $threadId Thread ID
	 * @param int $taskId Task ID returned when the summary was requested
	 * @return DataResponse<Http::STATUS_OK, array{taskId: int|null, status: string, summary: string|null}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Summary state returned
	 * 404: No such summary for this account and thread
	 */
	#[NoAdminRequired]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/summary/{taskId}')]
	public function show(int $threadId, int $taskId): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$task = $this->threadSummaryService->getTask($userId, $threadId, $taskId);
			return new DataResponse($this->threadSummaryService->serializeTask($task));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => 'Summary not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching thread summary: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['error' => 'Failed to fetch summary'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
