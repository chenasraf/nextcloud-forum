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
use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\GuestService;
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
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
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
		private GuestService $guestService,
		private AdminSettingsService $adminSettingsService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get posts by thread with first post separated
	 *
	 * Pagination runs over top-level replies. Every nested reply below the
	 * top-level replies of the page is returned in `nestedReplies`, oldest
	 * first; each post links to the post it answers through `parentPostId`.
	 * Deleted posts that still have visible replies are returned as
	 * placeholders with `deletedAt` set and no author or content.
	 *
	 * @param int $threadId Thread ID
	 * @param int $page Page number (1-indexed); 0 picks the start page
	 * @param int $perPage Number of top-level replies per page
	 * @param int $postId When page is 0, open the page that shows this post instead of the start page
	 * @return DataResponse<Http::STATUS_OK, array{firstPost: array<string, mixed>|null, replies: list<array<string, mixed>>, nestedReplies: list<array<string, mixed>>, pagination: array{page: int, perPage: int, total: int, totalPages: int, startPage: int, lastReadPostId: int|null}}, array{}>
	 *
	 * 200: Posts returned with pagination metadata
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'GET', url: '/api/threads/{threadId}/posts')]
	public function byThread(int $threadId, int $page = 0, int $perPage = 20, int $postId = 0): DataResponse {
		try {
			// Get current user ID
			$currentUserId = $this->userSession->getUser()?->getUID();

			// Count top-level replies (excluding first post)
			$totalReplies = $this->postMapper->countTopLevelReplies($threadId);
			$totalPages = max(1, (int)ceil($totalReplies / $perPage));

			// Determine the start page based on read status
			$startPage = $totalPages; // Default: last page (newest) for unread threads
			$lastReadPostId = null;

			if ($currentUserId !== null) {
				try {
					$readMarker = $this->readMarkerMapper->findByUserAndThread($currentUserId, $threadId);
					$lastReadPostId = $readMarker->getLastReadPostId();

					// Find the oldest unread reply
					$oldestUnreadReply = $this->postMapper->findOldestUnreadReply($threadId, (int)$lastReadPostId);
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

			// If page=0, open the page of the requested post, or the start page
			if ($page === 0) {
				$page = $postId > 0
					? (int)floor($this->postMapper->getReplyPosition($threadId, $postId) / $perPage) + 1
					: $startPage;
			}

			// Ensure page is within valid range
			$page = max(1, min($page, $totalPages));
			$offset = ($page - 1) * $perPage;

			// Fetch first post
			$firstPost = $this->postMapper->findFirstPostByThreadId($threadId);

			// Fetch top-level replies for the current page, then their subtrees
			$replies = $this->postMapper->findTopLevelReplies($threadId, $perPage, $offset);
			$nestedReplies = $this->withoutDeadBranches(
				$this->postMapper->findDescendantsByRootIds(array_map(fn ($p) => $p->getId(), $replies)),
			);

			// Prefetch BBCodes once for all posts to avoid repeated queries
			$bbcodes = $this->bbCodeMapper->findAllEnabled();

			// Collect all visible posts for reaction and author fetching
			$allPosts = array_filter(
				array_merge($firstPost !== null ? [$firstPost] : [], $replies, $nestedReplies),
				fn ($p) => $p->getDeletedAt() === null,
			);
			$postIds = array_values(array_map(fn ($p) => $p->getId(), $allPosts));

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

			// Get category ID for permission checks
			$categoryId = $this->permissionService->getCategoryIdFromThread($threadId);

			// Enrich first post
			$enrichedFirstPost = null;
			if ($firstPost !== null) {
				$firstPostReactions = $reactionsByPostId[$firstPost->getId()] ?? [];
				$enrichedFirstPost = $this->postEnrichmentService->enrichPost(
					$firstPost,
					$bbcodes,
					$firstPostReactions,
					$currentUserId,
					$authors[$firstPost->getAuthorId()] ?? null,
					$categoryId,
				);
			}

			// Enrich replies
			$enrichReply = function (\OCA\Forum\Db\Post $p) use ($bbcodes, $reactionsByPostId, $currentUserId, $authors, $categoryId): array {
				if ($p->getDeletedAt() !== null) {
					return $this->deletedPlaceholder($p);
				}
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				return $this->postEnrichmentService->enrichPost($p, $bbcodes, $postReactions, $currentUserId, $authors[$p->getAuthorId()] ?? null, $categoryId);
			};
			$enrichedReplies = array_map($enrichReply, $replies);
			$enrichedNestedReplies = array_map($enrichReply, $nestedReplies);

			return new DataResponse([
				'firstPost' => $enrichedFirstPost,
				'replies' => $enrichedReplies,
				'nestedReplies' => $enrichedNestedReplies,
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
			$this->logger->error('Error fetching posts by thread: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch posts'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Drop deleted nested replies that have no visible reply below them.
	 * Deleted posts that do have one stay, so their subtree keeps its place.
	 *
	 * @param array<\OCA\Forum\Db\Post> $posts Nested replies, oldest first
	 * @return list<\OCA\Forum\Db\Post>
	 */
	private function withoutDeadBranches(array $posts): array {
		$hasVisibleReply = [];
		$keep = [];
		// A reply is always newer than its parent, so walking newest first
		// settles every child before its parent
		foreach (array_reverse($posts) as $post) {
			$visible = $post->getDeletedAt() === null || isset($hasVisibleReply[$post->getId()]);
			if ($visible) {
				$keep[$post->getId()] = true;
				$parentId = $post->getParentPostId();
				if ($parentId !== null) {
					$hasVisibleReply[$parentId] = true;
				}
			}
		}

		return array_values(array_filter($posts, fn ($p) => isset($keep[$p->getId()])));
	}

	/**
	 * Serialize a deleted post that is kept in a thread to hold its replies
	 *
	 * @return array<string, mixed>
	 */
	private function deletedPlaceholder(\OCA\Forum\Db\Post $post): array {
		return [
			'id' => $post->getId(),
			'threadId' => $post->getThreadId(),
			'authorId' => '',
			'content' => '',
			'contentRaw' => '',
			'isEdited' => false,
			'isFirstPost' => false,
			'editedAt' => null,
			'createdAt' => $post->getCreatedAt(),
			'updatedAt' => $post->getUpdatedAt(),
			'deletedAt' => $post->getDeletedAt(),
			'parentPostId' => $post->getParentPostId(),
			'rootReplyId' => $post->getRootReplyId(),
			'author' => null,
			'reactions' => [],
		];
	}

	/**
	 * Get posts by author
	 *
	 * @param string $authorId Author user ID
	 * @param int<1, 200> $limit Maximum number of posts to return
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

			// Resolve category IDs for each post's thread (for edit history visibility)
			$threadIds = array_unique(array_map(fn ($p) => $p->getThreadId(), $posts));
			$categoryByThread = [];
			foreach ($threadIds as $tid) {
				try {
					$categoryByThread[$tid] = $this->permissionService->getCategoryIdFromThread($tid);
				} catch (\Exception $e) {
					// Skip if thread not found
				}
			}

			// Enrich posts with content, reactions, pre-fetched author data, and page number
			$perPage = 20;
			return new DataResponse(array_map(function ($p) use ($bbcodes, $reactionsByPostId, $currentUserId, $author, $perPage, $categoryByThread) {
				$postReactions = $reactionsByPostId[$p->getId()] ?? [];
				$categoryId = $categoryByThread[$p->getThreadId()] ?? null;
				$enriched = $this->postEnrichmentService->enrichPost($p, $bbcodes, $postReactions, $currentUserId, $author, $categoryId);

				// Calculate the page number for direct linking
				if (!$p->getIsFirstPost()) {
					try {
						$position = $this->postMapper->getReplyPosition($p->getThreadId(), $p->getId());
						$enriched['page'] = (int)floor($position / $perPage) + 1;
					} catch (\Exception $e) {
						// Fallback - page unknown
					}
				}

				return $enriched;
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
			$currentUserId = $this->userSession->getUser()?->getUID();
			$categoryId = $this->permissionService->getCategoryIdFromPost($id);
			/** @var array<string, mixed> $data */
			$data = $this->postEnrichmentService->enrichPost($post, [], [], $currentUserId, null, $categoryId);
			return new DataResponse($data);
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
	 * @param string $guestToken Guest session token (32-char hex, for unauthenticated users)
	 * @param int|null $parentPostId Post this reply answers; omitted, or the thread's first post, for a top-level reply. Ignored when nested replies are disabled
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Post created
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[NoCSRFRequired]
	#[RequirePermission('canReply', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	#[ApiRoute(verb: 'POST', url: '/api/posts')]
	public function create(int $threadId, string $content, string $guestToken = '', ?int $parentPostId = null): DataResponse {
		try {
			$user = $this->userSession->getUser();

			// Resolve author identity
			if ($user) {
				$authorId = $user->getUID();
			} elseif ($guestToken !== '') {
				try {
					$authorId = $this->guestService->resolveGuestIdentity($guestToken);
				} catch (\InvalidArgumentException $e) {
					return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}
			} else {
				return new DataResponse(['error' => 'User not authenticated'], Http::STATUS_UNAUTHORIZED);
			}

			$parent = null;
			if ($parentPostId !== null
				&& (int)$this->adminSettingsService->getSetting(AdminSettingsService::SETTING_MAX_REPLY_DEPTH) > 0) {
				try {
					$parent = $this->postMapper->find($parentPostId);
				} catch (DoesNotExistException $e) {
					return new DataResponse(['error' => 'Parent post not found'], Http::STATUS_BAD_REQUEST);
				}
				if ($parent->getThreadId() !== $threadId) {
					return new DataResponse(['error' => 'Parent post not found'], Http::STATUS_BAD_REQUEST);
				}
				// Answering the opening post is the same as replying to the thread
				if ($parent->getIsFirstPost()) {
					$parent = null;
				}
			}

			$post = new \OCA\Forum\Db\Post();
			$post->setThreadId($threadId);
			$post->setAuthorId($authorId);
			$post->setContent($content);
			$post->setIsEdited(false);
			$post->setIsFirstPost(false);
			$post->setParentPostId($parent?->getId());
			$post->setRootReplyId($parent === null ? null : ($parent->getRootReplyId() ?? $parent->getId()));
			$post->setCreatedAt(time());
			$post->setUpdatedAt(time());

			$createdPost = $this->postMapper->insert($post);

			// User-only operations (read markers, forum user stats, auto-subscribe)
			if ($user) {
				// Mark thread as read up to and including the new post
				try {
					$this->readMarkerMapper->createOrUpdate(
						$user->getUID(),
						$threadId,
						$createdPost->getId()
					);
				} catch (\Exception $e) {
					$this->logger->warning('Failed to update read marker after creating post: ' . $e->getMessage());
				}

				// Update forum user post count (auto-creates forum user if needed)
				try {
					$this->forumUserMapper->incrementPostCount($user->getUID());
				} catch (\Exception $e) {
					$this->logger->warning('Failed to update forum user post count: ' . $e->getMessage());
				}

				// Auto-subscribe the user to the thread if preference is enabled and not already subscribed
				try {
					$autoSubscribe = (bool)$this->userPreferencesService->getPreference(
						$user->getUID(),
						UserPreferencesService::PREF_AUTO_SUBSCRIBE_REPLIED_THREADS
					);

					if ($autoSubscribe && !$this->threadSubscriptionMapper->isUserSubscribed($user->getUID(), $threadId)) {
						$this->threadSubscriptionMapper->subscribe($user->getUID(), $threadId);
					}
				} catch (\Exception $e) {
					$this->logger->warning('Failed to auto-subscribe user to thread: ' . $e->getMessage());
				}
			}

			// Update the thread's post count and last reply info
			$thread = null;
			try {
				$thread = $this->threadMapper->find($threadId);
				$thread->setPostCount($thread->getPostCount() + 1);
				$thread->setLastPostId($createdPost->getId());
				$thread->setLastReplyAuthorId($createdPost->getAuthorId());
				$thread->setLastReplyAt($createdPost->getCreatedAt());
				$this->threadMapper->update($thread);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to update thread post count: ' . $e->getMessage());
			}

			// Update the category's post count
			if ($thread !== null) {
				try {
					$category = $this->categoryMapper->find($thread->getCategoryId());
					$category->setPostCount($category->getPostCount() + 1);
					$this->categoryMapper->update($category);
				} catch (\Exception $e) {
					$this->logger->warning('Failed to update category post count: ' . $e->getMessage());
				}
			}

			// Notify registered users about the new post
			try {
				$this->notificationService->notifyThreadSubscribers($threadId, $createdPost->getId(), $authorId);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send notifications for new post: ' . $e->getMessage());
			}

			// Notify mentioned users
			$mentionedUsers = [];
			try {
				$mentionedUsers = $this->notificationService->extractMentions($content);
				$this->notificationService->notifyMentionedUsers($createdPost->getId(), $threadId, $authorId, $mentionedUsers);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send mention notifications: ' . $e->getMessage());
			}

			// Notify the author of the post being replied to
			if ($parent !== null) {
				try {
					$this->notificationService->notifyPostReply($createdPost, $parent, $mentionedUsers);
				} catch (\Exception $e) {
					$this->logger->warning('Failed to send reply notification: ' . $e->getMessage());
				}
			}

			$currentUserId = $user?->getUID();
			$categoryId = $this->permissionService->getCategoryIdFromThread($threadId);
			/** @var array<string, mixed> $data */
			$data = $this->postEnrichmentService->enrichPost($createdPost, [], [], $currentUserId, null, $categoryId);
			return new DataResponse($data, Http::STATUS_CREATED);
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

			/** @var array<string, mixed> $data */
			$data = $this->postEnrichmentService->enrichPost($updatedPost, [], [], $user->getUID(), null, $categoryId);
			return new DataResponse($data);
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
				// If the deleted post was the last post, update lastPostId to the previous non-deleted post
				if ($thread->getLastPostId() === $post->getId()) {
					// Find the latest non-deleted post in this thread (excluding the one being deleted)
					$latestPost = $this->postMapper->findLatestByThreadId($thread->getId(), $post->getId());
					if ($latestPost) {
						$thread->setLastPostId($latestPost->getId());
						if (!$latestPost->getIsFirstPost()) {
							$thread->setLastReplyAuthorId($latestPost->getAuthorId());
							$thread->setLastReplyAt($latestPost->getCreatedAt());
						} else {
							// Only the first post remains — no replies
							$thread->setLastReplyAuthorId(null);
							$thread->setLastReplyAt(null);
						}
					} else {
						// No other posts in thread, set to null (or keep first post ID)
						$thread->setLastPostId(null);
						$thread->setLastReplyAuthorId(null);
						$thread->setLastReplyAt(null);
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
				$this->notificationService->dismissPostReplyNotification($id);
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
