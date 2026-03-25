<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\ModerationService;
use OCA\Forum\Service\PostEnrichmentService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ModerationController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private CategoryMapper $categoryMapper,
		private ModerationService $moderationService,
		private PostEnrichmentService $postEnrichmentService,
		private UserService $userService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List deleted threads
	 *
	 * @param int<1, 100> $limit Maximum results per page
	 * @param int $offset Pagination offset
	 * @param string $search Search term for thread titles
	 * @param string $sort Sort order: 'newest' or 'oldest'
	 * @return DataResponse<Http::STATUS_OK, array{items: list<array<string, mixed>>, total: int}, array{}>
	 *
	 * 200: Deleted threads returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessModeration')]
	#[ApiRoute(verb: 'GET', url: '/api/moderation/threads')]
	public function listDeletedThreads(int $limit = 20, int $offset = 0, string $search = '', string $sort = 'newest'): DataResponse {
		try {
			$threads = $this->threadMapper->findDeleted($limit, $offset, $search, $sort);
			$total = $this->threadMapper->countDeleted($search);

			// Collect unique author IDs and category IDs
			$authorIds = array_unique(array_map(fn ($t) => $t->getAuthorId(), $threads));
			$categoryIds = array_unique(array_map(fn ($t) => $t->getCategoryId(), $threads));

			// Enrich authors
			$authors = !empty($authorIds) ? $this->userService->enrichMultipleUsers($authorIds) : [];

			// Fetch category names
			$categories = [];
			foreach ($categoryIds as $catId) {
				try {
					$cat = $this->categoryMapper->find($catId);
					$categories[$catId] = $cat->getName();
				} catch (DoesNotExistException $e) {
					$categories[$catId] = null;
				}
			}

			$items = array_map(function ($thread) use ($authors, $categories) {
				$data = $thread->jsonSerialize();
				$data['author'] = $authors[$thread->getAuthorId()] ?? null;
				$data['categoryName'] = $categories[$thread->getCategoryId()] ?? null;
				return $data;
			}, $threads);

			return new DataResponse(['items' => $items, 'total' => $total]);
		} catch (\Exception $e) {
			$this->logger->error('Error listing deleted threads: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to list deleted threads'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single deleted thread with its posts
	 *
	 * @param int $id Thread ID
	 * @param int<1, 100> $postLimit Maximum posts per page
	 * @param int<0, max> $postOffset Post pagination offset
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread with posts returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessModeration')]
	#[ApiRoute(verb: 'GET', url: '/api/moderation/threads/{id}')]
	public function getDeletedThread(int $id, int $postLimit = 20, int $postOffset = 0): DataResponse {
		try {
			$thread = $this->threadMapper->findIncludingDeleted($id);
			$data = $thread->jsonSerialize();

			// Enrich thread author
			$authorData = $this->userService->enrichUserData($thread->getAuthorId());
			$data['author'] = $authorData;

			// Get category name
			try {
				$cat = $this->categoryMapper->find($thread->getCategoryId());
				$data['categoryName'] = $cat->getName();
			} catch (DoesNotExistException $e) {
				$data['categoryName'] = null;
			}

			// Get posts for this thread (including deleted), enriched with BBCode parsing
			$posts = $this->postMapper->findByThreadIdIncludingDeleted($thread->getId(), $postLimit, $postOffset);
			$postAuthorIds = array_unique(array_map(fn ($p) => $p->getAuthorId(), $posts));
			$postAuthors = !empty($postAuthorIds) ? $this->userService->enrichMultipleUsers($postAuthorIds) : [];

			$data['posts'] = array_map(function ($post) use ($postAuthors) {
				return $this->postEnrichmentService->enrichPost(
					$post,
					[],
					[],
					null,
					$postAuthors[$post->getAuthorId()] ?? null,
				);
			}, $posts);
			$data['totalPosts'] = $this->postMapper->countByThreadIdIncludingDeleted($thread->getId());

			return new DataResponse($data);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching deleted thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Restore a deleted thread
	 *
	 * @param int $id Thread ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, thread: array<string, mixed>}, array{}>
	 *
	 * 200: Thread restored
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessModeration')]
	#[ApiRoute(verb: 'POST', url: '/api/moderation/threads/{id}/restore')]
	public function restoreThread(int $id): DataResponse {
		try {
			$thread = $this->moderationService->restoreThread($id);
			return new DataResponse(['success' => true, 'thread' => $thread->jsonSerialize()]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->error('Error restoring thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to restore thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List deleted replies
	 *
	 * @param int<1, 100> $limit Maximum results per page
	 * @param int $offset Pagination offset
	 * @param string $search Search term for reply content
	 * @param string $sort Sort order: 'newest' or 'oldest'
	 * @return DataResponse<Http::STATUS_OK, array{items: list<array<string, mixed>>, total: int}, array{}>
	 *
	 * 200: Deleted replies returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessModeration')]
	#[ApiRoute(verb: 'GET', url: '/api/moderation/replies')]
	public function listDeletedReplies(int $limit = 20, int $offset = 0, string $search = '', string $sort = 'newest'): DataResponse {
		try {
			$posts = $this->postMapper->findDeletedReplies($limit, $offset, $search, $sort);
			$total = $this->postMapper->countDeletedReplies($search);

			// Collect unique author IDs and thread IDs
			$authorIds = array_unique(array_map(fn ($p) => $p->getAuthorId(), $posts));
			$threadIds = array_unique(array_map(fn ($p) => $p->getThreadId(), $posts));

			// Enrich authors
			$authors = !empty($authorIds) ? $this->userService->enrichMultipleUsers($authorIds) : [];

			// Fetch thread context for each reply
			$threadContext = [];
			foreach ($threadIds as $threadId) {
				try {
					$thread = $this->threadMapper->findIncludingDeleted($threadId);
					$threadContext[$threadId] = [
						'title' => $thread->getTitle(),
						'slug' => $thread->getSlug(),
					];
				} catch (DoesNotExistException $e) {
					$threadContext[$threadId] = ['title' => null, 'slug' => null];
				}
			}

			$items = array_map(function ($post) use ($authors, $threadContext) {
				$data = $this->postEnrichmentService->enrichPost(
					$post,
					[],
					[],
					null,
					$authors[$post->getAuthorId()] ?? null,
				);
				$ctx = $threadContext[$post->getThreadId()] ?? ['title' => null, 'slug' => null];
				$data['threadTitle'] = $ctx['title'];
				$data['threadSlug'] = $ctx['slug'];
				return $data;
			}, $posts);

			return new DataResponse(['items' => $items, 'total' => $total]);
		} catch (\Exception $e) {
			$this->logger->error('Error listing deleted replies: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to list deleted replies'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single deleted reply with thread context
	 *
	 * @param int $id Post ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Reply with context returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessModeration')]
	#[ApiRoute(verb: 'GET', url: '/api/moderation/replies/{id}')]
	public function getDeletedReply(int $id): DataResponse {
		try {
			$post = $this->postMapper->findIncludingDeleted($id);
			$data = $this->postEnrichmentService->enrichPost($post);

			// Add thread context
			try {
				$thread = $this->threadMapper->findIncludingDeleted($post->getThreadId());
				$data['threadTitle'] = $thread->getTitle();
				$data['threadSlug'] = $thread->getSlug();
				$data['categoryId'] = $thread->getCategoryId();
			} catch (DoesNotExistException $e) {
				$data['threadTitle'] = null;
				$data['threadSlug'] = null;
				$data['categoryId'] = null;
			}

			return new DataResponse($data);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Reply not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching deleted reply: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch reply'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Restore a deleted reply
	 *
	 * @param int $id Post ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, post: array<string, mixed>}, array{}>
	 *
	 * 200: Reply restored
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessModeration')]
	#[ApiRoute(verb: 'POST', url: '/api/moderation/replies/{id}/restore')]
	public function restoreReply(int $id): DataResponse {
		try {
			$post = $this->moderationService->restorePost($id);
			return new DataResponse(['success' => true, 'post' => $post->jsonSerialize()]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Reply not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->error('Error restoring reply: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to restore reply'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
