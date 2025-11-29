<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\ThreadEnrichmentService;
use OCA\Forum\Service\UserPreferencesService;
use OCA\Forum\Service\UserService;
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

class ThreadController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ThreadMapper $threadMapper,
		private CategoryMapper $categoryMapper,
		private PostMapper $postMapper,
		private ForumUserMapper $forumUserMapper,
		private ThreadSubscriptionMapper $threadSubscriptionMapper,
		private ThreadEnrichmentService $threadEnrichmentService,
		private UserPreferencesService $userPreferencesService,
		private UserService $userService,
		private PermissionService $permissionService,
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
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/threads')]
	public function index(): DataResponse {
		try {
			$threads = $this->threadMapper->findAll();

			// Extract unique author IDs
			$authorIds = array_unique(array_map(fn ($t) => $t->getAuthorId(), $threads));

			// Batch fetch author data (includes roles)
			$authors = $this->userService->enrichMultipleUsers($authorIds);

			// Enrich threads with pre-fetched author data
			return new DataResponse(array_map(function ($t) use ($authors) {
				return $this->threadEnrichmentService->enrichThread($t, $authors[$t->getAuthorId()]);
			}, $threads));
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
	#[PublicPage]
	#[RequirePermission('canView', resourceType: 'category', resourceIdParam: 'categoryId')]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{categoryId}/threads')]
	public function byCategory(int $categoryId, int $limit = 50, int $offset = 0): DataResponse {
		try {
			$threads = $this->threadMapper->findByCategoryId($categoryId, $limit, $offset);

			// Extract unique author IDs
			$authorIds = array_unique(array_map(fn ($t) => $t->getAuthorId(), $threads));

			// Batch fetch author data (includes roles)
			$authors = $this->userService->enrichMultipleUsers($authorIds);

			// Enrich threads with pre-fetched author data
			return new DataResponse(array_map(function ($t) use ($authors) {
				return $this->threadEnrichmentService->enrichThread($t, $authors[$t->getAuthorId()]);
			}, $threads));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching threads by category: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch threads'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get paginated threads by category
	 *
	 * @param int $categoryId Category ID
	 * @param int $page Page number (1-indexed)
	 * @param int $perPage Number of threads per page
	 * @return DataResponse<Http::STATUS_OK, array{threads: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, totalPages: int}}, array{}>
	 *
	 * 200: Threads returned with pagination metadata
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[RequirePermission('canView', resourceType: 'category', resourceIdParam: 'categoryId')]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{categoryId}/threads/paginated')]
	public function byCategoryPaginated(int $categoryId, int $page = 1, int $perPage = 20): DataResponse {
		try {
			// Count total threads in category
			$totalThreads = $this->threadMapper->countByCategoryId($categoryId);
			$totalPages = max(1, (int)ceil($totalThreads / $perPage));

			// Ensure page is within valid range
			$page = max(1, min($page, $totalPages));
			$offset = ($page - 1) * $perPage;

			// Fetch threads for the current page
			$threads = $this->threadMapper->findByCategoryId($categoryId, $perPage, $offset);

			// Extract unique author IDs
			$authorIds = array_unique(array_map(fn ($t) => $t->getAuthorId(), $threads));

			// Batch fetch author data (includes roles)
			$authors = $this->userService->enrichMultipleUsers($authorIds);

			// Enrich threads with pre-fetched author data
			$enrichedThreads = array_map(function ($t) use ($authors) {
				return $this->threadEnrichmentService->enrichThread($t, $authors[$t->getAuthorId()] ?? null);
			}, $threads);

			return new DataResponse([
				'threads' => $enrichedThreads,
				'pagination' => [
					'page' => $page,
					'perPage' => $perPage,
					'total' => $totalThreads,
					'totalPages' => $totalPages,
				],
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching paginated threads by category: ' . $e->getMessage());
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
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/users/{authorId}/threads')]
	public function byAuthor(string $authorId, int $limit = 50, int $offset = 0): DataResponse {
		try {
			$threads = $this->threadMapper->findByAuthorId($authorId, $limit, $offset);

			// For threads by a single author, we can optimize by fetching author data once
			$author = $this->userService->enrichUserData($authorId);

			// Enrich threads with pre-fetched author data
			return new DataResponse(array_map(function ($t) use ($author) {
				return $this->threadEnrichmentService->enrichThread($t, $author);
			}, $threads));
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
	#[PublicPage]
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

			return new DataResponse($this->threadEnrichmentService->enrichThread($thread));
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
	#[PublicPage]
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

			return new DataResponse($this->threadEnrichmentService->enrichThread($thread));
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
			$post->setIsEdited(false);
			$post->setIsFirstPost(true);
			$post->setCreatedAt(time());
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$createdPost = $this->postMapper->insert($post);

			// Update thread with post count and last post
			// Note: post_count does NOT include the first post (is_first_post=true)
			$createdThread->setPostCount(0);
			$createdThread->setLastPostId($createdPost->getId());
			$this->threadMapper->update($createdThread);

			// Update category counts (thread count only, first post doesn't count)
			try {
				$category = $this->categoryMapper->find($categoryId);
				$category->setThreadCount($category->getThreadCount() + 1);
				$this->categoryMapper->update($category);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category counts: ' . $e->getMessage());
			}

			// Update forum user (thread count only, first post doesn't count)
			try {
				$this->forumUserMapper->incrementThreadCount($user->getUID());
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update forum user: ' . $e->getMessage());
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
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'id')]
	#[ApiRoute(verb: 'PUT', url: '/api/threads/{id}')]
	public function update(int $id, ?string $title = null, ?bool $isLocked = null, ?bool $isPinned = null, ?bool $isHidden = null): DataResponse {
		try {
			$user = $this->userSession->getUser();

			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$thread = $this->threadMapper->find($id);

			// Check if user is the author or has moderation permission
			$isAuthor = $thread->getAuthorId() === $user->getUID();
			$canModerate = $this->permissionService->hasCategoryPermission(
				$user->getUID(),
				$thread->getCategoryId(),
				'canModerate'
			);

			// Title can be updated by author or moderator
			if ($title !== null) {
				if (!$isAuthor && !$canModerate) {
					return new DataResponse(
						['error' => 'You do not have permission to edit this thread title'],
						Http::STATUS_FORBIDDEN
					);
				}
				$thread->setTitle($title);
			}

			// Lock, pin, and hidden status can only be updated by moderators
			if (($isLocked !== null || $isPinned !== null || $isHidden !== null) && !$canModerate) {
				return new DataResponse(
					['error' => 'You do not have permission to modify thread status'],
					Http::STATUS_FORBIDDEN
				);
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
			return new DataResponse($this->threadEnrichmentService->enrichThread($updatedThread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Move thread to a different category
	 *
	 * @param int $id Thread ID
	 * @param int $categoryId New category ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Thread moved successfully
	 */
	#[NoAdminRequired]
	#[RequirePermission('canModerate', resourceType: 'category', resourceIdFromThreadId: 'id')]
	#[ApiRoute(verb: 'PUT', url: '/api/threads/{id}/move')]
	public function move(int $id, int $categoryId): DataResponse {
		try {
			$user = $this->userSession->getUser();

			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$thread = $this->threadMapper->find($id);

			// Verify the target category exists and user has permission to post there
			try {
				$targetCategory = $this->categoryMapper->find($categoryId);
			} catch (DoesNotExistException $e) {
				return new DataResponse(['error' => 'Target category not found'], Http::STATUS_NOT_FOUND);
			}

			// Check if user has moderation permission on target category
			$canModerateTarget = $this->permissionService->hasCategoryPermission(
				$user->getUID(),
				$categoryId,
				'canModerate'
			);

			if (!$canModerateTarget) {
				return new DataResponse(
					['error' => 'You do not have permission to move threads to this category'],
					Http::STATUS_FORBIDDEN
				);
			}

			$oldCategoryId = $thread->getCategoryId();

			// Update thread category
			$thread->setCategoryId($categoryId);
			$thread->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Thread */
			$updatedThread = $this->threadMapper->update($thread);

			// Update category counts for both old and new categories
			try {
				// Decrement old category counts
				$oldCategory = $this->categoryMapper->find($oldCategoryId);
				$oldCategory->setThreadCount(max(0, $oldCategory->getThreadCount() - 1));
				$oldCategory->setPostCount(max(0, $oldCategory->getPostCount() - $thread->getPostCount()));
				$this->categoryMapper->update($oldCategory);

				// Increment new category counts
				$targetCategory->setThreadCount($targetCategory->getThreadCount() + 1);
				$targetCategory->setPostCount($targetCategory->getPostCount() + $thread->getPostCount());
				$this->categoryMapper->update($targetCategory);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category counts after thread move: ' . $e->getMessage());
				// Don't fail the request if category update fails
			}

			return new DataResponse($this->threadEnrichmentService->enrichThread($updatedThread));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Thread not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error moving thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to move thread'], Http::STATUS_INTERNAL_SERVER_ERROR);
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
			return new DataResponse($this->threadEnrichmentService->enrichThread($updatedThread));
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
			return new DataResponse($this->threadEnrichmentService->enrichThread($updatedThread));
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

			// Update author's forum user (decrement thread count and all posts in this thread)
			try {
				$this->forumUserMapper->decrementThreadCount($thread->getAuthorId());
				// Decrement post count by the number of posts in this thread
				if ($thread->getPostCount() > 0) {
					$this->forumUserMapper->decrementPostCount($thread->getAuthorId(), $thread->getPostCount());
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update forum user after thread deletion: ' . $e->getMessage());
				// Don't fail the request if forum user update fails
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
