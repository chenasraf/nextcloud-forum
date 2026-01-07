<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReactionMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\NotificationService;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\PostEnrichmentService;
use OCA\Forum\Service\PostHistoryService;
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

class PostController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PostMapper $postMapper,
		private ThreadMapper $threadMapper,
		private CategoryMapper $categoryMapper,
		private ForumUserMapper $forumUserMapper,
		private ReactionMapper $reactionMapper,
		private BBCodeService $bbCodeService,
		private BBCodeMapper $bbCodeMapper,
		private PermissionService $permissionService,
		private ReadMarkerMapper $readMarkerMapper,
		private NotificationService $notificationService,
		private PostEnrichmentService $postEnrichmentService,
		private PostHistoryService $postHistoryService,
		private UserService $userService,
		private UserPreferencesService $userPreferencesService,
		private ThreadSubscriptionMapper $threadSubscriptionMapper,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get posts by thread
	 *
	 * @param int $threadId Thread ID
	 * @param int $limit Maximum number of posts to return
	 * @param int $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Posts returned
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/posts')]
	public function byThread(int $threadId, int $limit = 50, int $offset = 0): DataResponse {
		try {
			$posts = $this->postMapper->findByThreadId($threadId, $limit, $offset);

			// Prefetch BBCodes once for all posts to avoid repeated queries
			$bbcodes = $this->bbCodeMapper->findAllEnabled();

			// Fetch reactions for all posts at once (performance optimization)
			$postIds = array_map(fn ($p) => $p->getId(), $posts);
			$reactions = $this->reactionMapper->findByPostIds($postIds);

			// Group reactions by post ID
			$reactionsByPostId = [];
			foreach ($reactions as $reaction) {
				$postId = $reaction->getPostId();
				if (!isset($reactionsByPostId[$postId])) {
					$reactionsByPostId[$postId] = [];
				}
				$reactionsByPostId[$postId][] = $reaction;
			}

			// Get current user ID to mark user's reactions
			$currentUserId = $this->userSession->getUser()?->getUID();

			// Extract unique author IDs
			$authorIds = array_unique(array_map(fn ($p) => $p->getAuthorId(), $posts));

			// Batch fetch author data (includes roles)
			$authors = $this->userService->enrichMultipleUsers($authorIds);

			// Enrich posts with content, reactions, and pre-fetched author data
			return new DataResponse(array_map(function ($p) use ($bbcodes, $reactionsByPostId, $currentUserId, $authors) {
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				return $this->postEnrichmentService->enrichPost($p, $bbcodes, $postReactions, $currentUserId, $authors[$p->getAuthorId()]);
			}, $posts));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching posts by thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch posts'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get paginated posts by thread with first post separated
	 *
	 * @param int $threadId Thread ID
	 * @param int $page Page number (1-indexed)
	 * @param int $perPage Number of replies per page
	 * @return DataResponse<Http::STATUS_OK, array{firstPost: array<string, mixed>|null, replies: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, totalPages: int, startPage: int, lastReadPostId: int|null}}, array{}>
	 *
	 * 200: Posts returned with pagination metadata
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/posts/paginated')]
	public function byThreadPaginated(int $threadId, int $page = 0, int $perPage = 20): DataResponse {
		try {
			// Get current user ID
			$currentUserId = $this->userSession->getUser()?->getUID();

			// Count total replies (excluding first post)
			$totalReplies = $this->postMapper->countRepliesByThreadId($threadId);
			$totalPages = max(1, (int)ceil($totalReplies / $perPage));

			// Determine the start page based on read status
			$startPage = $totalPages; // Default: last page (newest) for unread threads
			$lastReadPostId = null;

			if ($currentUserId !== null) {
				try {
					$readMarker = $this->readMarkerMapper->findByUserAndThread($currentUserId, $threadId);
					$lastReadPostId = $readMarker->getLastReadPostId();

					// Find the oldest unread reply
					$oldestUnreadReply = $this->postMapper->findOldestUnreadReply($threadId, $lastReadPostId);
					if ($oldestUnreadReply !== null) {
						// Calculate which page this reply is on
						$position = $this->postMapper->getReplyPosition($threadId, $oldestUnreadReply->getId());
						$startPage = (int)floor($position / $perPage) + 1;
					} else {
						// All replies are read, go to last page
						$startPage = $totalPages;
					}
				} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
					// No read marker = never read = go to last page (newest)
					$startPage = $totalPages;
				}
			}

			// If page=0, use the calculated start page
			if ($page === 0) {
				$page = $startPage;
			}

			// Ensure page is within valid range
			$page = max(1, min($page, $totalPages));
			$offset = ($page - 1) * $perPage;

			// Fetch first post
			$firstPost = $this->postMapper->findFirstPostByThreadId($threadId);

			// Fetch replies for the current page
			$replies = $this->postMapper->findRepliesByThreadId($threadId, $perPage, $offset);

			// Prefetch BBCodes once for all posts to avoid repeated queries
			$bbcodes = $this->bbCodeMapper->findAllEnabled();

			// Collect all posts for reaction fetching
			$allPosts = $firstPost !== null ? array_merge([$firstPost], $replies) : $replies;
			$postIds = array_map(fn ($p) => $p->getId(), $allPosts);

			// Fetch reactions for all posts at once (performance optimization)
			$reactions = $this->reactionMapper->findByPostIds($postIds);

			// Group reactions by post ID
			$reactionsByPostId = [];
			foreach ($reactions as $reaction) {
				$postId = $reaction->getPostId();
				if (!isset($reactionsByPostId[$postId])) {
					$reactionsByPostId[$postId] = [];
				}
				$reactionsByPostId[$postId][] = $reaction;
			}

			// Extract unique author IDs
			$authorIds = array_unique(array_map(fn ($p) => $p->getAuthorId(), $allPosts));

			// Batch fetch author data (includes roles)
			$authors = $this->userService->enrichMultipleUsers($authorIds);

			// Enrich first post
			$enrichedFirstPost = null;
			if ($firstPost !== null) {
				$firstPostReactions = $reactionsByPostId[$firstPost->getId()] ?? [];
				$enrichedFirstPost = $this->postEnrichmentService->enrichPost(
					$firstPost,
					$bbcodes,
					$firstPostReactions,
					$currentUserId,
					$authors[$firstPost->getAuthorId()] ?? null
				);
			}

			// Enrich replies
			$enrichedReplies = array_map(function ($p) use ($bbcodes, $reactionsByPostId, $currentUserId, $authors) {
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				return $this->postEnrichmentService->enrichPost($p, $bbcodes, $postReactions, $currentUserId, $authors[$p->getAuthorId()] ?? null);
			}, $replies);

			return new DataResponse([
				'firstPost' => $enrichedFirstPost,
				'replies' => $enrichedReplies,
				'pagination' => [
					'page' => $page,
					'perPage' => $perPage,
					'total' => $totalReplies,
					'totalPages' => $totalPages,
					'startPage' => $startPage,
					'lastReadPostId' => $lastReadPostId,
				],
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching paginated posts by thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch posts'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get posts by author
	 *
	 * @param string $authorId Author user ID
	 * @param int $limit Maximum number of posts to return
	 * @param int $offset Offset for pagination
	 * @param string $excludeFirstPosts Whether to exclude first posts (1 or 0)
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Posts returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/users/{authorId}/posts')]
	public function byAuthor(string $authorId, int $limit = 50, int $offset = 0, string $excludeFirstPosts = '0'): DataResponse {
		try {
			$posts = $this->postMapper->findByAuthorId($authorId, $limit, $offset, $excludeFirstPosts === '1');

			// Prefetch BBCodes once for all posts to avoid repeated queries
			$bbcodes = $this->bbCodeMapper->findAllEnabled();

			// Fetch reactions for all posts at once (performance optimization)
			$postIds = array_map(fn ($p) => $p->getId(), $posts);
			$reactions = $this->reactionMapper->findByPostIds($postIds);

			// Group reactions by post ID
			$reactionsByPostId = [];
			foreach ($reactions as $reaction) {
				$postId = $reaction->getPostId();
				if (!isset($reactionsByPostId[$postId])) {
					$reactionsByPostId[$postId] = [];
				}
				$reactionsByPostId[$postId][] = $reaction;
			}

			// Get current user ID to mark user's reactions
			$currentUserId = $this->userSession->getUser()?->getUID();

			// For posts by a single author, we can optimize by fetching author data once
			$author = $this->userService->enrichUserData($authorId);

			// Enrich posts with content, reactions, and pre-fetched author data
			return new DataResponse(array_map(function ($p) use ($bbcodes, $reactionsByPostId, $currentUserId, $author) {
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				return $this->postEnrichmentService->enrichPost($p, $bbcodes, $postReactions, $currentUserId, $author);
			}, $posts));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching posts by author: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch posts'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single post
	 *
	 * @param int $id Post ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Post returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromPostId: 'id')]
	#[ApiRoute(verb: 'GET', url: '/api/posts/{id}')]
	public function show(int $id): DataResponse {
		try {
			$post = $this->postMapper->find($id);
			return new DataResponse($this->postEnrichmentService->enrichPost($post));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new post
	 *
	 * @param int $threadId Thread ID
	 * @param string $content Post content
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Post created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canReply', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'POST', url: '/api/posts')]
	public function create(int $threadId, string $content): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$post = new \OCA\Forum\Db\Post();
			$post->setThreadId($threadId);
			$post->setAuthorId($user->getUID());
			$post->setContent($content);
			$post->setIsEdited(false);
			$post->setIsFirstPost(false);
			$post->setCreatedAt(time());
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$createdPost = $this->postMapper->insert($post);

			// Mark thread as read up to and including the new post
			try {
				$this->readMarkerMapper->createOrUpdate(
					$user->getUID(),
					$threadId,
					$createdPost->getId()
				);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update read marker after creating post: ' . $e->getMessage());
				// Don't fail the request if read marker update fails
			}

			// Update the thread's post count and timestamps
			try {
				$thread = $this->threadMapper->find($threadId);
				$thread->setPostCount($thread->getPostCount() + 1);
				$thread->setLastPostId($createdPost->getId());
				$thread->setUpdatedAt(time());
				$this->threadMapper->update($thread);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update thread post count: ' . $e->getMessage());
				// Don't fail the request if thread update fails
			}

			// Update forum user post count (auto-creates forum user if needed)
			try {
				$this->forumUserMapper->incrementPostCount($user->getUID());
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update forum user post count: ' . $e->getMessage());
				// Don't fail the request if forum user update fails
			}

			// Update the category's post count
			try {
				$category = $this->categoryMapper->find($thread->getCategoryId());
				$category->setPostCount($category->getPostCount() + 1);
				$this->categoryMapper->update($category);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category post count: ' . $e->getMessage());
				// Don't fail the request if category update fails
			}

			// Notify registered users about the new post
			try {
				$this->notificationService->notifyThreadSubscribers($threadId, $createdPost->getId(), $user->getUID());
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send notifications for new post: ' . $e->getMessage());
				// Don't fail the request if notification sending fails
			}

			// Notify mentioned users
			try {
				$mentionedUsers = $this->notificationService->extractMentions($content);
				$this->notificationService->notifyMentionedUsers($createdPost->getId(), $threadId, $user->getUID(), $mentionedUsers);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send mention notifications: ' . $e->getMessage());
				// Don't fail the request if mention notification sending fails
			}

			// Auto-subscribe the user to the thread if preference is enabled and not already subscribed
			try {
				$autoSubscribe = $this->userPreferencesService->getPreference(
					$user->getUID(),
					UserPreferencesService::PREF_AUTO_SUBSCRIBE_REPLIED_THREADS
				);

				if ($autoSubscribe && !$this->threadSubscriptionMapper->isUserSubscribed($user->getUID(), $threadId)) {
					$this->threadSubscriptionMapper->subscribe($user->getUID(), $threadId);
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to auto-subscribe user to thread: ' . $e->getMessage());
				// Don't fail the request if auto-subscribe fails
			}

			return new DataResponse($this->postEnrichmentService->enrichPost($createdPost), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a post
	 *
	 * @param int $id Post ID
	 * @param string|null $content Post content
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Post updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/posts/{id}')]
	public function update(int $id, ?string $content = null): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$post = $this->postMapper->find($id);
			$oldContent = $post->getContent();

			// Check if user is the author OR has moderator permission OR is admin/moderator
			$isAuthor = $post->getAuthorId() === $user->getUID();
			$categoryId = $this->permissionService->getCategoryIdFromPost($id);
			$isModerator = $this->permissionService->hasCategoryPermission($user->getUID(), $categoryId, 'canModerate');
			$isAdminOrMod = $this->permissionService->hasAdminOrModeratorRole($user->getUID());

			if (!$isAuthor && !$isModerator && !$isAdminOrMod) {
				return new DataResponse(['error' => 'Insufficient permissions to edit this post'], Http::STATUS_FORBIDDEN);
			}

			if ($content !== null && $oldContent !== $content) {
				// Save the old content to history before updating
				try {
					$this->postHistoryService->saveHistory($post, $user->getUID());
				} catch (\Exception $e) {
					$this->logger->warning('Failed to save post edit history: ' . $e->getMessage());
					// Don't fail the request if history save fails
				}

				$post->setContent($content);
				$post->setIsEdited(true);
				$post->setEditedAt(time());
			}
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$updatedPost = $this->postMapper->update($post);

			// Handle mention notification changes (notify new mentions, remove old ones)
			if ($content !== null && $oldContent !== $content) {
				try {
					$this->notificationService->handleMentionChanges(
						$id,
						$post->getThreadId(),
						$post->getAuthorId(),
						$oldContent,
						$content
					);
				} catch (\Exception $e) {
					$this->logger->warning('Failed to update mention notifications: ' . $e->getMessage());
					// Don't fail the request if mention notification update fails
				}
			}

			return new DataResponse($this->postEnrichmentService->enrichPost($updatedPost));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a post (soft delete)
	 *
	 * @param int $id Post ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Post deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/posts/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$post = $this->postMapper->find($id);

			// Check if user is the author OR has moderator permission OR is admin/moderator
			$isAuthor = $post->getAuthorId() === $user->getUID();
			$categoryId = $this->permissionService->getCategoryIdFromPost($id);
			$isModerator = $this->permissionService->hasCategoryPermission($user->getUID(), $categoryId, 'canModerate');
			$isAdminOrMod = $this->permissionService->hasAdminOrModeratorRole($user->getUID());

			if (!$isAuthor && !$isModerator && !$isAdminOrMod) {
				return new DataResponse(['error' => 'Insufficient permissions to delete this post'], Http::STATUS_FORBIDDEN);
			}

			// Soft delete the post
			$post->setDeletedAt(time());
			$post->setUpdatedAt(time());
			$this->postMapper->update($post);

			// Update thread post count and lastPostId
			try {
				$thread = $this->threadMapper->find($post->getThreadId());
				// Only decrement post count for reply posts (not first posts)
				if (!$post->getIsFirstPost()) {
					$thread->setPostCount(max(0, $thread->getPostCount() - 1));
				}
				$thread->setUpdatedAt(time());

				// If the deleted post was the last post, update lastPostId to the previous non-deleted post
				if ($thread->getLastPostId() === $post->getId()) {
					// Find the latest non-deleted post in this thread (excluding the one being deleted)
					$latestPost = $this->postMapper->findLatestByThreadId($thread->getId(), $post->getId());
					if ($latestPost) {
						$thread->setLastPostId($latestPost->getId());
					} else {
						// No other posts in thread, set to null (or keep first post ID)
						$thread->setLastPostId(null);
					}
				}

				$this->threadMapper->update($thread);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update thread after post deletion: ' . $e->getMessage());
				// Don't fail the request if thread update fails
			}

			// Update forum user - decrement post count, and thread count if it's the first post
			try {
				if ($post->getIsFirstPost()) {
					// First post: decrement thread count only
					$this->forumUserMapper->decrementThreadCount($post->getAuthorId());
				} else {
					// Reply post: decrement post count only
					$this->forumUserMapper->decrementPostCount($post->getAuthorId());
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update forum user after post deletion: ' . $e->getMessage());
				// Don't fail the request if forum user update fails
			}

			// Update category post count (only for reply posts, not first posts)
			try {
				if (!$post->getIsFirstPost()) {
					$category = $this->categoryMapper->find($categoryId);
					$category->setPostCount(max(0, $category->getPostCount() - 1));
					$this->categoryMapper->update($category);
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category post count after post deletion: ' . $e->getMessage());
				// Don't fail the request if category update fails
			}

			// Dismiss all mention notifications for this post
			try {
				$this->notificationService->dismissAllMentionNotifications($id, $post->getContent(), $post->getAuthorId());
			} catch (\Exception $e) {
				$this->logger->warning('Failed to dismiss mention notifications after post deletion: ' . $e->getMessage());
				// Don't fail the request if notification dismissal fails
			}

			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
