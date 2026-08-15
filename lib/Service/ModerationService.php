<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\Bookmark;
use OCA\Forum\Db\BookmarkMapper;
use OCA\Forum\Db\Draft;
use OCA\Forum\Db\DraftMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostHistoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReactionMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Service for moderation actions (restore and permanently delete content)
 */
class ModerationService {
	public function __construct(
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private StatsService $statsService,
		private ReactionMapper $reactionMapper,
		private PostHistoryMapper $postHistoryMapper,
		private BookmarkMapper $bookmarkMapper,
		private ReadMarkerMapper $readMarkerMapper,
		private ThreadSubscriptionMapper $threadSubscriptionMapper,
		private DraftMapper $draftMapper,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Restore a soft-deleted thread
	 *
	 * @param int $threadId Thread ID to restore
	 * @return Thread The restored thread
	 * @throws \OCP\AppFramework\Db\DoesNotExistException If thread not found
	 * @throws \InvalidArgumentException If thread is not deleted
	 */
	public function restoreThread(int $threadId): Thread {
		$thread = $this->threadMapper->findIncludingDeleted($threadId);

		if ($thread->getDeletedAt() === null) {
			throw new \InvalidArgumentException('Thread is not deleted');
		}

		$thread->setDeletedAt(null);
		$thread->setUpdatedAt(time());
		$this->threadMapper->update($thread);

		// Rebuild stats from scratch — safer than manual increment
		$this->statsService->rebuildCategoryStats($thread->getCategoryId());
		$this->statsService->rebuildThreadStats($threadId);
		$this->statsService->rebuildUserStats($thread->getAuthorId());

		$this->logger->info("Restored deleted thread $threadId");

		return $thread;
	}

	/**
	 * Restore a soft-deleted reply post
	 *
	 * @param int $postId Post ID to restore
	 * @return Post The restored post
	 * @throws \OCP\AppFramework\Db\DoesNotExistException If post not found
	 * @throws \InvalidArgumentException If post is not deleted or is a first post
	 */
	public function restorePost(int $postId): Post {
		$post = $this->postMapper->findIncludingDeleted($postId);

		if ($post->getDeletedAt() === null) {
			throw new \InvalidArgumentException('Post is not deleted');
		}

		if ($post->getIsFirstPost()) {
			throw new \InvalidArgumentException('First posts must be restored via thread restore');
		}

		$post->setDeletedAt(null);
		$post->setUpdatedAt(time());
		$this->postMapper->update($post);

		// Rebuild stats from scratch
		$thread = $this->threadMapper->findIncludingDeleted($post->getThreadId());
		$this->statsService->rebuildThreadStats($post->getThreadId());
		$this->statsService->rebuildCategoryStats($thread->getCategoryId());
		$this->statsService->rebuildUserStats($post->getAuthorId());

		$this->logger->info("Restored deleted post $postId");

		return $post;
	}

	/**
	 * Permanently delete a soft-deleted thread and all of its associated data.
	 *
	 * This removes the thread, every post in it, and all related reactions,
	 * edit history, bookmarks, read markers, subscriptions and reply drafts.
	 * The action cannot be undone.
	 *
	 * @param int $threadId Thread ID to permanently delete
	 * @throws \OCP\AppFramework\Db\DoesNotExistException If thread not found
	 * @throws \InvalidArgumentException If thread is not soft-deleted
	 */
	public function permanentlyDeleteThread(int $threadId): void {
		$thread = $this->threadMapper->findIncludingDeleted($threadId);

		if ($thread->getDeletedAt() === null) {
			throw new \InvalidArgumentException('Thread is not deleted');
		}

		$postIds = $this->postMapper->findIdsByThreadId($threadId);

		$this->db->beginTransaction();
		try {
			// Post-scoped data
			$this->reactionMapper->deleteByPostIds($postIds);
			$this->postHistoryMapper->deleteByPostIds($postIds);
			$this->bookmarkMapper->deleteByEntityTypeAndIds(Bookmark::ENTITY_TYPE_POST, $postIds);

			// Thread-scoped data
			$this->postMapper->deleteByThreadId($threadId);
			$this->bookmarkMapper->deleteByEntity(Bookmark::ENTITY_TYPE_THREAD, $threadId);
			$this->readMarkerMapper->deleteByThreadId($threadId);
			$this->threadSubscriptionMapper->deleteByThreadId($threadId);
			$this->draftMapper->deleteByEntityTypeAndParentId(Draft::ENTITY_TYPE_POST, $threadId);

			$this->threadMapper->delete($thread);

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$this->logger->info("Permanently deleted thread $threadId with " . count($postIds) . ' post(s)');
	}

	/**
	 * Permanently delete a soft-deleted reply post and all of its associated data.
	 *
	 * This removes the post and all related reactions, edit history and
	 * bookmarks. First posts must be deleted via thread deletion. The action
	 * cannot be undone.
	 *
	 * @param int $postId Post ID to permanently delete
	 * @throws \OCP\AppFramework\Db\DoesNotExistException If post not found
	 * @throws \InvalidArgumentException If post is not soft-deleted or is a first post
	 */
	public function permanentlyDeleteReply(int $postId): void {
		$post = $this->postMapper->findIncludingDeleted($postId);

		if ($post->getDeletedAt() === null) {
			throw new \InvalidArgumentException('Post is not deleted');
		}

		if ($post->getIsFirstPost()) {
			throw new \InvalidArgumentException('First posts must be deleted via thread deletion');
		}

		$this->db->beginTransaction();
		try {
			$this->reactionMapper->deleteByPostIds([$postId]);
			$this->postHistoryMapper->deleteByPostId($postId);
			$this->bookmarkMapper->deleteByEntity(Bookmark::ENTITY_TYPE_POST, $postId);

			$this->postMapper->deleteById($postId);

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$this->logger->info("Permanently deleted post $postId");
	}

	/**
	 * Permanently delete multiple soft-deleted threads.
	 *
	 * Each thread is deleted independently; a failure on one does not abort the
	 * rest. Returns the IDs that were deleted and, per failure, the ID and reason.
	 *
	 * @param list<int> $ids Thread IDs to permanently delete
	 * @return array{deleted: list<int>, failed: list<array{id: int, error: string}>}
	 */
	public function permanentlyDeleteThreads(array $ids): array {
		$deleted = [];
		$failed = [];
		foreach ($ids as $id) {
			try {
				$this->permanentlyDeleteThread($id);
				$deleted[] = $id;
			} catch (\Throwable $e) {
				$this->logger->warning("Failed to permanently delete thread $id: " . $e->getMessage());
				$failed[] = ['id' => $id, 'error' => $e->getMessage()];
			}
		}
		return ['deleted' => $deleted, 'failed' => $failed];
	}

	/**
	 * Permanently delete multiple soft-deleted reply posts.
	 *
	 * Each reply is deleted independently; a failure on one does not abort the
	 * rest. Returns the IDs that were deleted and, per failure, the ID and reason.
	 *
	 * @param list<int> $ids Post IDs to permanently delete
	 * @return array{deleted: list<int>, failed: list<array{id: int, error: string}>}
	 */
	public function permanentlyDeleteReplies(array $ids): array {
		$deleted = [];
		$failed = [];
		foreach ($ids as $id) {
			try {
				$this->permanentlyDeleteReply($id);
				$deleted[] = $id;
			} catch (\Throwable $e) {
				$this->logger->warning("Failed to permanently delete post $id: " . $e->getMessage());
				$failed[] = ['id' => $id, 'error' => $e->getMessage()];
			}
		}
		return ['deleted' => $deleted, 'failed' => $failed];
	}
}
