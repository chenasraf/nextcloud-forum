<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\Post;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\PostEnrichmentService;
use OCA\Forum\Service\SearchService;
use OCA\Forum\Service\ThreadEnrichmentService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SearchController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private SearchService $searchService,
		private ThreadMapper $threadMapper,
		private PostEnrichmentService $postEnrichmentService,
		private ThreadEnrichmentService $threadEnrichmentService,
		private UserService $userService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Search forum threads and posts
	 *
	 * @param string $q Search query (supports quoted phrases, AND/OR operators, parentheses, -exclusions)
	 * @param bool $searchThreads Include threads in search (title + first post content)
	 * @param bool $searchPosts Include reply posts in search
	 * @param int|null $categoryId Optional category ID filter
	 * @param int $limit Maximum results per type
	 * @param int $offset Results offset per type
	 * @return DataResponse<Http::STATUS_OK, array{threads: array<string, mixed>, posts: array<string, mixed>, threadCount: int, postCount: int, query: string}, array{}>
	 *
	 * 200: Search results returned
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/search')]
	public function index(
		string $q = '',
		bool $searchThreads = true,
		bool $searchPosts = true,
		?int $categoryId = null,
		int $limit = 50,
		int $offset = 0,
	): DataResponse {
		try {
			$user = $this->userSession->getUser();
			$userId = $user?->getUID();

			// Validate query
			$q = trim($q);
			if (empty($q)) {
				return new DataResponse([
					'error' => 'Search query is required'
				], Http::STATUS_BAD_REQUEST);
			}

			// Validate search scope
			if (!$searchThreads && !$searchPosts) {
				return new DataResponse([
					'error' => 'At least one search scope must be selected (threads or posts)'
				], Http::STATUS_BAD_REQUEST);
			}

			// Perform search
			$results = $this->searchService->search(
				$q,
				$userId,
				$searchThreads,
				$searchPosts,
				$categoryId,
				$limit,
				$offset
			);

			// Collect all unique author IDs from both threads and posts
			$allAuthorIds = [];
			foreach ($results['threads'] as $thread) {
				$allAuthorIds[] = $thread->getAuthorId();
			}
			foreach ($results['posts'] as $post) {
				$allAuthorIds[] = $post->getAuthorId();
			}
			$allAuthorIds = array_unique($allAuthorIds);

			// Batch fetch all author data once
			$authors = $this->userService->enrichMultipleUsers($allAuthorIds);

			// Enrich threads with pre-fetched author data
			$enrichedThreads = array_map(function ($thread) use ($authors) {
				return $this->threadEnrichmentService->enrichThread($thread, $authors[$thread->getAuthorId()]);
			}, $results['threads']);

			// Enrich posts with pre-fetched author data and thread context
			$enrichedPosts = array_map(function ($post) use ($authors) {
				$enriched = $this->postEnrichmentService->enrichPost($post, [], [], null, $authors[$post->getAuthorId()]);
				// Add thread info for context
				try {
					$thread = $this->threadMapper->find($post->getThreadId());
					$enriched['threadTitle'] = $thread->getTitle();
					$enriched['threadSlug'] = $thread->getSlug();
				} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
					// Thread not found (deleted or inaccessible)
					$enriched['threadTitle'] = null;
					$enriched['threadSlug'] = null;
				}

				return $enriched;
			}, $results['posts']);

			return new DataResponse([
				'threads' => $enrichedThreads,
				'posts' => $enrichedPosts,
				'threadCount' => $results['threadCount'],
				'postCount' => $results['postCount'],
				'query' => $q,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error performing search: ' . $e->getMessage(), [
				'exception' => $e,
				'trace' => $e->getTraceAsString(),
			]);
			return new DataResponse([
				'error' => 'Failed to perform search'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
