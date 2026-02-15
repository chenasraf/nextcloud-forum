<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\Bookmark;
use OCA\Forum\Db\BookmarkMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\ThreadEnrichmentService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class BookmarkController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private BookmarkMapper $bookmarkMapper,
		private ThreadMapper $threadMapper,
		private ReadMarkerMapper $readMarkerMapper,
		private ThreadEnrichmentService $threadEnrichmentService,
		private UserService $userService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Add a bookmark for a thread
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread bookmarked
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/threads/{threadId}/bookmark')]
	public function bookmarkThread(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$bookmark = $this->bookmarkMapper->bookmarkThread($user->getUID(), $threadId);
			return new DataResponse([
				'success' => true,
				'bookmark' => $bookmark->jsonSerialize(),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error bookmarking thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to bookmark thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Remove a bookmark from a thread
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Bookmark removed
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/threads/{threadId}/bookmark')]
	public function unbookmarkThread(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$this->bookmarkMapper->unbookmarkThread($user->getUID(), $threadId);
			return new DataResponse(['success' => true]);
		} catch (\Exception $e) {
			$this->logger->error('Error removing bookmark from thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to remove bookmark'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Check if current user has bookmarked a thread
	 *
	 * @param int $threadId Thread ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Bookmark status returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/bookmark')]
	public function isThreadBookmarked(int $threadId): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$isBookmarked = $this->bookmarkMapper->isThreadBookmarked($user->getUID(), $threadId);
			return new DataResponse(['isBookmarked' => $isBookmarked]);
		} catch (\Exception $e) {
			$this->logger->error('Error checking bookmark status: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to check bookmark status'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all bookmarked threads for the current user (paginated)
	 * Includes read marker information for unread status
	 *
	 * @param int $page Page number (1-indexed)
	 * @param int $perPage Number of threads per page
	 * @return DataResponse<Http::STATUS_OK, array{threads: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, totalPages: int}, readMarkers: array<string, array{entityId: int, lastReadPostId: int, readAt: int}>}, array{}>
	 *
	 * 200: Bookmarked threads returned with pagination and read markers
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/bookmarks')]
	public function index(int $page = 1, int $perPage = 20): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$userId = $user->getUID();

			// Count total thread bookmarks for pagination
			$totalBookmarks = $this->bookmarkMapper->countThreadBookmarksByUserId($userId);
			$totalPages = max(1, (int)ceil($totalBookmarks / $perPage));

			// Ensure page is within valid range
			$page = max(1, min($page, $totalPages));
			$offset = ($page - 1) * $perPage;

			// Fetch thread bookmarks for the current page (ordered by most recent first)
			$bookmarks = $this->bookmarkMapper->findThreadBookmarksByUserId($userId, $perPage, $offset);

			// Extract thread IDs to fetch the actual thread data
			$threadIds = array_map(fn ($b) => $b->getEntityId(), $bookmarks);

			if (empty($threadIds)) {
				return new DataResponse([
					'threads' => [],
					'pagination' => [
						'page' => $page,
						'perPage' => $perPage,
						'total' => 0,
						'totalPages' => 1,
					],
					'readMarkers' => [],
				]);
			}

			// Fetch threads by IDs
			$threads = $this->threadMapper->findByIds($threadIds);

			// Create a map for ordering threads by bookmark order (most recent first)
			$threadMap = [];
			foreach ($threads as $thread) {
				$threadMap[$thread->getId()] = $thread;
			}

			// Order threads by bookmark order and enrich them
			$orderedThreads = [];

			// Extract unique author IDs for batch enrichment
			$authorIds = array_unique(array_map(fn ($t) => $t->getAuthorId(), $threads));
			$authors = $this->userService->enrichMultipleUsers($authorIds);

			foreach ($bookmarks as $bookmark) {
				$threadId = $bookmark->getEntityId();
				if (isset($threadMap[$threadId])) {
					$thread = $threadMap[$threadId];
					$enriched = $this->threadEnrichmentService->enrichThread(
						$thread,
						$authors[$thread->getAuthorId()] ?? null
					);
					// Add bookmark timestamp
					$enriched['bookmarkedAt'] = $bookmark->getCreatedAt();
					$orderedThreads[] = $enriched;
				}
			}

			// Fetch read markers for these threads
			$readMarkers = [];
			$markers = $this->readMarkerMapper->findByUserAndThreads($userId, $threadIds);
			foreach ($markers as $marker) {
				$readMarkers[$marker->getEntityId()] = [
					'entityId' => $marker->getEntityId(),
					'lastReadPostId' => $marker->getLastReadPostId(),
					'readAt' => $marker->getReadAt(),
				];
			}

			return new DataResponse([
				'threads' => $orderedThreads,
				'pagination' => [
					'page' => $page,
					'perPage' => $perPage,
					'total' => $totalBookmarks,
					'totalPages' => $totalPages,
				],
				'readMarkers' => $readMarkers,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching bookmarked threads: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch bookmarked threads'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
