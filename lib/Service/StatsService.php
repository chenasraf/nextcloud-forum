<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class StatsService {
	public function __construct(
		private IDBConnection $db,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Create user statistics for all users in the system (including those who haven't posted)
	 *
	 * @return array{users: int, updated: int, created: int} Statistics about the creation
	 */
	public function rebuildAllUserStats(): array {
		// Get all user IDs from Nextcloud
		$users = [];
		$this->userManager->callForAllUsers(function ($user) use (&$users) {
			$users[] = $user->getUID();
		});

		$updated = 0;
		$created = 0;

		foreach ($users as $userId) {
			$wasCreated = $this->rebuildUserStats($userId);
			if ($wasCreated) {
				$created++;
			} else {
				$updated++;
			}
		}

		return [
			'users' => count($users),
			'updated' => $updated,
			'created' => $created,
		];
	}

	/**
	 * Rebuild statistics for a single user
	 *
	 * @param string $userId The user ID to rebuild stats for
	 * @return bool True if stats were created, false if they were updated
	 */
	public function rebuildUserStats(string $userId): bool {
		// Count non-deleted threads created by this user
		$threadQb = $this->db->getQueryBuilder();
		$threadQb->select($threadQb->func()->count('*', 'count'))
			->from('forum_threads')
			->where($threadQb->expr()->eq('author_id', $threadQb->createNamedParameter($userId)))
			->andWhere($threadQb->expr()->isNull('deleted_at'));
		$threadResult = $threadQb->executeQuery();
		$threadCount = (int)($threadResult->fetchOne() ?? 0);
		$threadResult->closeCursor();

		// Count non-deleted posts created by this user (from non-deleted threads)
		// Exclude is_first_post posts as they are counted as threads
		$postQb = $this->db->getQueryBuilder();
		$postQb->select($postQb->func()->count('*', 'count'))
			->from('forum_posts', 'p')
			->innerJoin('p', 'forum_threads', 't', $postQb->expr()->eq('p.thread_id', 't.id'))
			->where($postQb->expr()->eq('p.author_id', $postQb->createNamedParameter($userId)))
			->andWhere($postQb->expr()->isNull('p.deleted_at'))
			->andWhere($postQb->expr()->isNull('t.deleted_at'))
			->andWhere($postQb->expr()->eq('p.is_first_post', $postQb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)));
		$postResult = $postQb->executeQuery();
		$postCount = (int)($postResult->fetchOne() ?? 0);
		$postResult->closeCursor();

		// Get the timestamp of the last non-deleted post (from non-deleted threads)
		$lastPostQb = $this->db->getQueryBuilder();
		$lastPostQb->select('p.created_at')
			->from('forum_posts', 'p')
			->innerJoin('p', 'forum_threads', 't', $lastPostQb->expr()->eq('p.thread_id', 't.id'))
			->where($lastPostQb->expr()->eq('p.author_id', $lastPostQb->createNamedParameter($userId)))
			->andWhere($lastPostQb->expr()->isNull('p.deleted_at'))
			->andWhere($lastPostQb->expr()->isNull('t.deleted_at'))
			->orderBy('p.created_at', 'DESC')
			->setMaxResults(1);
		$lastPostResult = $lastPostQb->executeQuery();
		$lastPostAt = $lastPostResult->fetchOne();
		$lastPostResult->closeCursor();

		// Check if forum user record already exists
		$checkQb = $this->db->getQueryBuilder();
		$checkQb->select('user_id')
			->from('forum_users')
			->where($checkQb->expr()->eq('user_id', $checkQb->createNamedParameter($userId)));
		$checkResult = $checkQb->executeQuery();
		$exists = $checkResult->fetch();
		$checkResult->closeCursor();

		$timestamp = time();

		if ($exists) {
			// Update existing record
			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('forum_users')
				->set('thread_count', $updateQb->createNamedParameter($threadCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->set('post_count', $updateQb->createNamedParameter($postCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->set('updated_at', $updateQb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->where($updateQb->expr()->eq('user_id', $updateQb->createNamedParameter($userId)));

			if ($lastPostAt) {
				$updateQb->set('last_post_at', $updateQb->createNamedParameter((int)$lastPostAt, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT));
			}

			$updateQb->executeStatement();
			return false;
		} else {
			// Create new record
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('forum_users')
				->values([
					'user_id' => $insertQb->createNamedParameter($userId),
					'thread_count' => $insertQb->createNamedParameter($threadCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'post_count' => $insertQb->createNamedParameter($postCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'last_post_at' => $insertQb->createNamedParameter($lastPostAt ? (int)$lastPostAt : null, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $insertQb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'updated_at' => $insertQb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				]);

			try {
				$insertQb->executeStatement();
				return true;
			} catch (\Exception $e) {
				// If insert fails (race condition), try updating instead
				$this->logger->warning('Failed to create forum user record, attempting update', [
					'userId' => $userId,
					'exception' => $e->getMessage(),
				]);

				$updateQb = $this->db->getQueryBuilder();
				$updateQb->update('forum_users')
					->set('thread_count', $updateQb->createNamedParameter($threadCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
					->set('post_count', $updateQb->createNamedParameter($postCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
					->set('updated_at', $updateQb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
					->where($updateQb->expr()->eq('user_id', $updateQb->createNamedParameter($userId)));

				if ($lastPostAt) {
					$updateQb->set('last_post_at', $updateQb->createNamedParameter((int)$lastPostAt, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT));
				}

				$updateQb->executeStatement();
				return false;
			}
		}
	}

	/**
	 * Rebuild thread and post counts for all categories
	 *
	 * @return array{categories: int, updated: int} Statistics about the rebuild
	 */
	public function rebuildAllCategoryStats(): array {
		// Get all category IDs
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('forum_categories');
		$result = $qb->executeQuery();
		$categoryIds = [];
		while ($row = $result->fetch()) {
			$categoryIds[] = (int)$row['id'];
		}
		$result->closeCursor();

		$updated = 0;
		foreach ($categoryIds as $categoryId) {
			$this->rebuildCategoryStats($categoryId);
			$updated++;
		}

		return [
			'categories' => count($categoryIds),
			'updated' => $updated,
		];
	}

	/**
	 * Rebuild statistics for a single category
	 *
	 * @param int $categoryId The category ID to rebuild stats for
	 * @return void
	 */
	public function rebuildCategoryStats(int $categoryId): void {
		// Count non-deleted threads in this category
		$threadQb = $this->db->getQueryBuilder();
		$threadQb->select($threadQb->func()->count('*', 'count'))
			->from('forum_threads')
			->where($threadQb->expr()->eq('category_id', $threadQb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($threadQb->expr()->isNull('deleted_at'));
		$threadResult = $threadQb->executeQuery();
		$threadCount = (int)($threadResult->fetchOne() ?? 0);
		$threadResult->closeCursor();

		// Count non-deleted posts in non-deleted threads in this category (excluding first posts)
		$postQb = $this->db->getQueryBuilder();
		$postQb->select($postQb->func()->count('*', 'count'))
			->from('forum_posts', 'p')
			->innerJoin('p', 'forum_threads', 't', $postQb->expr()->eq('p.thread_id', 't.id'))
			->where($postQb->expr()->eq('t.category_id', $postQb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($postQb->expr()->isNull('p.deleted_at'))
			->andWhere($postQb->expr()->isNull('t.deleted_at'))
			->andWhere($postQb->expr()->eq('p.is_first_post', $postQb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)));
		$postResult = $postQb->executeQuery();
		$postCount = (int)($postResult->fetchOne() ?? 0);
		$postResult->closeCursor();

		// Update category stats
		$updateQb = $this->db->getQueryBuilder();
		$updateQb->update('forum_categories')
			->set('thread_count', $updateQb->createNamedParameter($threadCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('post_count', $updateQb->createNamedParameter($postCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('updated_at', $updateQb->createNamedParameter(time(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
		$updateQb->executeStatement();
	}

	/**
	 * Rebuild post counts for all threads
	 *
	 * @return array{threads: int, updated: int} Statistics about the rebuild
	 */
	public function rebuildAllThreadStats(): array {
		// Get all non-deleted thread IDs
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('forum_threads')
			->where($qb->expr()->isNull('deleted_at'));
		$result = $qb->executeQuery();
		$threadIds = [];
		while ($row = $result->fetch()) {
			$threadIds[] = (int)$row['id'];
		}
		$result->closeCursor();

		$updated = 0;
		foreach ($threadIds as $threadId) {
			$this->rebuildThreadStats($threadId);
			$updated++;
		}

		return [
			'threads' => count($threadIds),
			'updated' => $updated,
		];
	}

	/**
	 * Rebuild statistics for a single thread
	 *
	 * @param int $threadId The thread ID to rebuild stats for
	 * @return void
	 */
	public function rebuildThreadStats(int $threadId): void {
		// Count non-deleted posts in this thread (excluding first post)
		$postQb = $this->db->getQueryBuilder();
		$postQb->select($postQb->func()->count('*', 'count'))
			->from('forum_posts')
			->where($postQb->expr()->eq('thread_id', $postQb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($postQb->expr()->isNull('deleted_at'))
			->andWhere($postQb->expr()->eq('is_first_post', $postQb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)));
		$postResult = $postQb->executeQuery();
		$postCount = (int)($postResult->fetchOne() ?? 0);
		$postResult->closeCursor();

		// Update thread stats
		$updateQb = $this->db->getQueryBuilder();
		$updateQb->update('forum_threads')
			->set('post_count', $updateQb->createNamedParameter($postCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('updated_at', $updateQb->createNamedParameter(time(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
		$updateQb->executeStatement();
	}
}
