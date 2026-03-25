<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use Psr\Log\LoggerInterface;

/**
 * Service for moderation actions (restore deleted content)
 */
class ModerationService {
	public function __construct(
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private StatsService $statsService,
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
}
