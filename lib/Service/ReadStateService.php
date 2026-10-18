<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Thread;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Resolves what a user has read, combining per-thread read markers with the
 * per-category "mark as read" watermarks.
 *
 * A thread marker records the last post read in that thread. A category
 * watermark records when the user marked the whole category as read; every
 * post created up to that moment counts as read.
 */
class ReadStateService {
	public function __construct(
		private ReadMarkerMapper $readMarkerMapper,
		private PostMapper $postMapper,
		private CategoryMapper $categoryMapper,
		private PermissionService $permissionService,
	) {
	}

	/**
	 * Get the unread state of each thread for a user
	 *
	 * `unreadReplyCount` is null for threads the user has never seen (no read
	 * marker, and created after the category watermark), since every post in
	 * them is new and a count would only repeat the reply total.
	 *
	 * @param array<Thread> $threads
	 * @return array<int, array{isUnread: bool, unreadReplyCount: int|null}> keyed by thread ID
	 */
	public function getThreadStates(string $userId, array $threads): array {
		if (empty($threads)) {
			return [];
		}

		$threadIds = array_map(fn (Thread $t) => $t->getId(), $threads);
		$unreadCounts = $this->postMapper->countUnreadByThreads($userId, $threadIds);

		$markedThreadIds = [];
		foreach ($this->readMarkerMapper->findByUserAndThreads($userId, $threadIds) as $marker) {
			$markedThreadIds[$marker->getEntityId()] = true;
		}
		$watermarks = $this->getCategoryWatermarks($userId);

		$states = [];
		foreach ($threads as $thread) {
			$count = $unreadCounts[$thread->getId()] ?? 0;
			$watermark = $watermarks[$thread->getCategoryId()] ?? null;
			$seen = isset($markedThreadIds[$thread->getId()])
				|| ($watermark !== null && $thread->getCreatedAt() <= $watermark);
			$states[$thread->getId()] = [
				'isUnread' => $count > 0,
				'unreadReplyCount' => $seen ? $count : null,
			];
		}
		return $states;
	}

	/**
	 * Get the ID of the last post a user has read in a thread
	 *
	 * @return int|null The post ID, or null if the user has read nothing in the thread
	 */
	public function getLastReadPostId(string $userId, int $threadId, int $categoryId): ?int {
		$lastReadPostId = null;
		try {
			$lastReadPostId = $this->readMarkerMapper->findByUserAndThread($userId, $threadId)->getLastReadPostId();
		} catch (DoesNotExistException $e) {
			// No thread marker; the category watermark may still cover some posts
		}

		$watermark = $this->getCategoryWatermarks($userId)[$categoryId] ?? null;
		if ($watermark !== null) {
			$coveredPostId = $this->postMapper->findLastIdCreatedUpTo($threadId, $watermark);
			if ($coveredPostId !== null) {
				$lastReadPostId = max($lastReadPostId ?? 0, $coveredPostId);
			}
		}

		return $lastReadPostId;
	}

	/**
	 * Mark a category and all of its subcategories as read
	 *
	 * Subcategories the user cannot view are skipped.
	 *
	 * @return list<int> IDs of the categories marked as read
	 */
	public function markCategoryAsRead(string $userId, int $categoryId): array {
		$accessible = array_flip($this->permissionService->getAccessibleCategories($userId));

		$categoryIds = [$categoryId];
		foreach ($this->categoryMapper->findChildren($categoryId) as $child) {
			$categoryIds[] = $child->getId();
		}

		$marked = [];
		foreach ($categoryIds as $id) {
			if (!isset($accessible[$id])) {
				continue;
			}
			$this->readMarkerMapper->createOrUpdateCategoryMarker($userId, $id);
			$marked[] = $id;
		}
		return $marked;
	}

	/**
	 * @return array<int, int> categoryId => watermark timestamp
	 */
	private function getCategoryWatermarks(string $userId): array {
		$watermarks = [];
		foreach ($this->readMarkerMapper->findCategoryMarkersByUserId($userId) as $marker) {
			$watermarks[$marker->getEntityId()] = $marker->getReadAt();
		}
		return $watermarks;
	}
}
