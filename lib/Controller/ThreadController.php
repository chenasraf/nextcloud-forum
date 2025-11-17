<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCA\Forum\Db\UserStatsMapper;
use OCA\Forum\Service\UserPreferencesService;
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
		private CategoryMapper $categoryMapper,
		private PostMapper $postMapper,
		private UserStatsMapper $userStatsMapper,
		private ThreadSubscriptionMapper $threadSubscriptionMapper,
		private UserPreferencesService $userPreferencesService,
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
	#[RequirePermission('canView', resourceType: 'category', resourceIdParam: 'categoryId')]
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
	 * Get threads by author
	 *
	 * @param string $authorId Author user ID
	 * @param int $limit Maximum number of threads to return
	 * @param int $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Threads returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/users/{authorId}/threads')]
	public function byAuthor(string $authorId, int $limit = 50, int $offset = 0): DataResponse {
		try {
			$threads = $this->threadMapper->findByAuthorId($authorId, $limit, $offset);
			return new DataResponse(array_map(fn ($t) => Thread::enrichThread($t), $threads));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching threads by author: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch threads'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single thread
	 *
	 * @param int $id Thread ID
	 * @param string $incrementView Whether to increment view count (1 or 0)
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'id')]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{id}')]
	public function show(int $id, string $incrementView = '1'): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);

			// Increment view count only if requested
			if ($incrementView === '1') {
				$thread->setViewCount($thread->getViewCount() + 1);
				/** @var \OCA\Forum\Db\Thread */
				$thread = $this->threadMapper->update($thread);
			}

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
	 * @param string $incrementView Whether to increment view count (1 or 0)
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/threads/slug/{slug}')]
	public function bySlug(string $slug, string $incrementView = '1'): DataResponse {
		try {
			$thread = $this->threadMapper->findBySlug($slug);

			// Increment view count only if requested
			if ($incrementView === '1') {
				$thread->setViewCount($thread->getViewCount() + 1);
				/** @var \OCA\Forum\Db\Thread */
				$thread = $this->threadMapper->update($thread);
			}

			return new DataResponse(Thread::enrichThread($thread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching thread by slug: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new thread with initial post
	 *
	 * @param int $categoryId Category ID
	 * @param string $title Thread title
	 * @param string $content Initial post content
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Thread created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canPost', resourceType: 'category', resourceIdBody: 'categoryId')]
	#[ApiRoute(verb: 'POST', url: '/api/threads')]
	public function create(int $categoryId, string $title, string $content): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Generate slug from title
			$slug = $this->generateSlug($title);

			// Ensure slug is unique
			$slug = $this->ensureUniqueSlug($slug);

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

			// Create the initial post
			$post = new \OCA\Forum\Db\Post();
			$post->setThreadId($createdThread->getId());
			$post->setAuthorId($user->getUID());
			$post->setContent($content);
			$post->setSlug('post-' . uniqid());
			$post->setIsEdited(false);
			$post->setIsFirstPost(true);
			$post->setCreatedAt(time());
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$createdPost = $this->postMapper->insert($post);

			// Update thread with post count and last post
			$createdThread->setPostCount(1);
			$createdThread->setLastPostId($createdPost->getId());
			$this->threadMapper->update($createdThread);

			// Update category counts (thread count and post count)
			try {
				$category = $this->categoryMapper->find($categoryId);
				$category->setThreadCount($category->getThreadCount() + 1);
				$category->setPostCount($category->getPostCount() + 1);
				$this->categoryMapper->update($category);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category counts: ' . $e->getMessage());
			}

			// Update user stats (post count and thread count, auto-creates stats if needed)
			try {
				$this->userStatsMapper->incrementPostCount($user->getUID());
				$this->userStatsMapper->incrementThreadCount($user->getUID());
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update user stats: ' . $e->getMessage());
			}

			// Auto-subscribe the thread creator to receive notifications (if preference is enabled)
			try {
				$autoSubscribe = $this->userPreferencesService->getPreference(
					$user->getUID(),
					UserPreferencesService::PREF_AUTO_SUBSCRIBE_CREATED_THREADS
				);

				if ($autoSubscribe) {
					$this->threadSubscriptionMapper->subscribe($user->getUID(), $createdThread->getId());
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to subscribe thread creator: ' . $e->getMessage());
			}

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
	#[RequirePermission('canModerate', resourceType: 'category', resourceIdFromThreadId: 'id')]
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
	 * Toggle thread lock status
	 *
	 * @param int $id Thread ID
	 * @param bool $locked New lock status
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread lock status updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canModerate', resourceType: 'category', resourceIdFromThreadId: 'id')]
	#[ApiRoute(verb: 'PUT', url: '/api/threads/{id}/lock')]
	public function setLocked(int $id, bool $locked): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);
			$thread->setIsLocked($locked);
			$thread->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Thread */
			$updatedThread = $this->threadMapper->update($thread);
			return new DataResponse(Thread::enrichThread($updatedThread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating thread lock status: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update thread lock status'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Toggle thread pin status
	 *
	 * @param int $id Thread ID
	 * @param bool $pinned New pin status
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread pin status updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canModerate', resourceType: 'category', resourceIdFromThreadId: 'id')]
	#[ApiRoute(verb: 'PUT', url: '/api/threads/{id}/pin')]
	public function setPinned(int $id, bool $pinned): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);
			$thread->setIsPinned($pinned);
			$thread->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Thread */
			$updatedThread = $this->threadMapper->update($thread);
			return new DataResponse(Thread::enrichThread($updatedThread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating thread pin status: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update thread pin status'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a thread (soft delete)
	 *
	 * @param int $id Thread ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, categorySlug: string}, array{}>
	 *
	 * 200: Thread deleted
	 */
	#[NoAdminRequired]
	#[RequirePermission('canModerate', resourceType: 'category', resourceIdFromThreadId: 'id')]
	#[ApiRoute(verb: 'DELETE', url: '/api/threads/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$thread = $this->threadMapper->find($id);

			// Get category for slug and count updates
			$category = $this->categoryMapper->find($thread->getCategoryId());
			$categorySlug = $category->getSlug();

			// Soft delete the thread
			$thread->setDeletedAt(time());
			$thread->setUpdatedAt(time());
			$this->threadMapper->update($thread);

			// Update category counts (decrement thread count and post count)
			try {
				$category->setThreadCount(max(0, $category->getThreadCount() - 1));
				$category->setPostCount(max(0, $category->getPostCount() - $thread->getPostCount()));
				$this->categoryMapper->update($category);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category counts after thread deletion: ' . $e->getMessage());
				// Don't fail the request if category update fails
			}

			// Update author's user stats (decrement thread count and all posts in this thread)
			try {
				$this->userStatsMapper->decrementThreadCount($thread->getAuthorId());
				// Decrement post count by the number of posts in this thread
				if ($thread->getPostCount() > 0) {
					$this->userStatsMapper->decrementPostCount($thread->getAuthorId(), $thread->getPostCount());
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update user stats after thread deletion: ' . $e->getMessage());
				// Don't fail the request if stats update fails
			}

			return new DataResponse([
				'success' => true,
				'categorySlug' => $categorySlug,
			]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Generate a URL-friendly slug from a string
	 *
	 * @param string $text The text to convert to a slug
	 * @return string A URL-friendly slug
	 */
	private function generateSlug(string $text): string {
		// Convert to lowercase
		$slug = mb_strtolower($text, 'UTF-8');

		// Replace spaces and underscores with hyphens
		$slug = preg_replace('/[\s_]+/', '-', $slug);

		// Remove all non-word chars except hyphens
		$slug = preg_replace('/[^\w\-]+/u', '', $slug);

		// Replace multiple hyphens with single hyphen
		$slug = preg_replace('/-+/', '-', $slug);

		// Remove leading/trailing hyphens
		$slug = trim($slug, '-');

		// If slug is empty after processing, generate a random one
		if (empty($slug)) {
			$slug = 'thread-' . uniqid();
		}

		return $slug;
	}

	/**
	 * Ensure slug is unique by appending a number if necessary
	 *
	 * @param string $slug The base slug to make unique
	 * @return string A unique slug
	 */
	private function ensureUniqueSlug(string $slug): string {
		$originalSlug = $slug;
		$counter = 1;

		// Keep trying until we find a unique slug
		while (true) {
			try {
				// Try to find a thread with this slug
				$this->threadMapper->findBySlug($slug);
				// If we get here, slug exists, try the next one
				$slug = $originalSlug . '-' . $counter;
				$counter++;
			} catch (DoesNotExistException $e) {
				// Slug doesn't exist, we can use it
				return $slug;
			}
		}
	}
}
