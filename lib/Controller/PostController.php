<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReactionMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserStatsMapper;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\NotificationService;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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
		private UserStatsMapper $userStatsMapper,
		private ReactionMapper $reactionMapper,
		private BBCodeService $bbCodeService,
		private BBCodeMapper $bbCodeMapper,
		private PermissionService $permissionService,
		private ReadMarkerMapper $readMarkerMapper,
		private NotificationService $notificationService,
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

			// Enrich posts with content and reactions
			return new DataResponse(array_map(function ($p) use ($bbcodes, $reactionsByPostId, $currentUserId) {
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				return Post::enrichPostContent($p, $bbcodes, $postReactions, $currentUserId);
			}, $posts));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching posts by thread: ' . $e->getMessage());
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

			// Enrich posts with content and reactions
			return new DataResponse(array_map(function ($p) use ($bbcodes, $reactionsByPostId, $currentUserId) {
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				return Post::enrichPostContent($p, $bbcodes, $postReactions, $currentUserId);
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
			return new DataResponse(Post::enrichPostContent($post));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a post by slug
	 *
	 * @param string $slug Post slug
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Post returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/posts/slug/{slug}')]
	public function bySlug(string $slug): DataResponse {
		try {
			$post = $this->postMapper->findBySlug($slug);
			return new DataResponse(Post::enrichPostContent($post));
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Post not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching post by slug: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch post'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new post
	 *
	 * @param int $threadId Thread ID
	 * @param string $content Post content
	 * @param string|null $slug Post slug (auto-generated if not provided)
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Post created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canReply', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'POST', url: '/api/posts')]
	public function create(int $threadId, string $content, ?string $slug = null): DataResponse {
		try {
			$user = $this->userSession->getUser();
			if (!$user) {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			// Auto-generate slug if not provided
			if ($slug === null || $slug === '') {
				$slug = 'post-' . uniqid();
			}

			$post = new \OCA\Forum\Db\Post();
			$post->setThreadId($threadId);
			$post->setAuthorId($user->getUID());
			$post->setContent($content);
			$post->setSlug($slug);
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

			// Update user stats post count (auto-creates stats if needed)
			try {
				$this->userStatsMapper->incrementPostCount($user->getUID());
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update user stats post count: ' . $e->getMessage());
				// Don't fail the request if stats update fails
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

			return new DataResponse(Post::enrichPostContent($createdPost), Http::STATUS_CREATED);
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

			// Check if user is the author OR has moderator permission OR is admin/moderator
			$isAuthor = $post->getAuthorId() === $user->getUID();
			$categoryId = $this->permissionService->getCategoryIdFromPost($id);
			$isModerator = $this->permissionService->hasCategoryPermission($user->getUID(), $categoryId, 'canModerate');
			$isAdminOrMod = $this->permissionService->hasAdminOrModeratorRole($user->getUID());

			if (!$isAuthor && !$isModerator && !$isAdminOrMod) {
				return new DataResponse(['error' => 'Insufficient permissions to edit this post'], Http::STATUS_FORBIDDEN);
			}

			if ($content !== null) {
				$post->setContent($content);
				$post->setIsEdited(true);
				$post->setEditedAt(time());
			}
			$post->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Post */
			$updatedPost = $this->postMapper->update($post);
			return new DataResponse(Post::enrichPostContent($updatedPost));
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
				$thread->setPostCount(max(0, $thread->getPostCount() - 1));
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

			// Update user stats - decrement post count, and thread count if it's the first post
			try {
				$this->userStatsMapper->decrementPostCount($post->getAuthorId());

				// If this is the first post of a thread, also decrement thread count
				if ($post->getIsFirstPost()) {
					$this->userStatsMapper->decrementThreadCount($post->getAuthorId());
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update user stats after post deletion: ' . $e->getMessage());
				// Don't fail the request if stats update fails
			}

			// Update category post count
			try {
				$category = $this->categoryMapper->find($categoryId);
				$category->setPostCount(max(0, $category->getPostCount() - 1));
				$this->categoryMapper->update($category);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update category post count after post deletion: ' . $e->getMessage());
				// Don't fail the request if category update fails
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
