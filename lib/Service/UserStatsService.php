<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class UserStatsService {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Rebuild user statistics from actual post and thread counts
	 *
	 * @return array{users: int, updated: int, created: int} Statistics about the rebuild
	 */
	public function rebuildAllUserStats(): array {
		// Get all users who have posted or created threads
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('author_id')
			->from('forum_posts')
			->where($qb->expr()->isNotNull('author_id'));
		$result = $qb->executeQuery();
		$users = [];
		while ($row = $result->fetch()) {
			$users[] = $row['author_id'];
		}
		$result->closeCursor();

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
		// Count threads created by this user
		$threadQb = $this->db->getQueryBuilder();
		$threadQb->select($threadQb->func()->count('*', 'count'))
			->from('forum_threads')
			->where($threadQb->expr()->eq('author_id', $threadQb->createNamedParameter($userId)));
		$threadResult = $threadQb->executeQuery();
		$threadCount = (int)($threadResult->fetchOne() ?? 0);
		$threadResult->closeCursor();

		// Count posts created by this user
		$postQb = $this->db->getQueryBuilder();
		$postQb->select($postQb->func()->count('*', 'count'))
			->from('forum_posts')
			->where($postQb->expr()->eq('author_id', $postQb->createNamedParameter($userId)));
		$postResult = $postQb->executeQuery();
		$postCount = (int)($postResult->fetchOne() ?? 0);
		$postResult->closeCursor();

		// Get the timestamp of the last post
		$lastPostQb = $this->db->getQueryBuilder();
		$lastPostQb->select('created_at')
			->from('forum_posts')
			->where($lastPostQb->expr()->eq('author_id', $lastPostQb->createNamedParameter($userId)))
			->orderBy('created_at', 'DESC')
			->setMaxResults(1);
		$lastPostResult = $lastPostQb->executeQuery();
		$lastPostAt = $lastPostResult->fetchOne();
		$lastPostResult->closeCursor();

		// Check if user stats already exist
		$checkQb = $this->db->getQueryBuilder();
		$checkQb->select('id')
			->from('forum_user_stats')
			->where($checkQb->expr()->eq('user_id', $checkQb->createNamedParameter($userId)));
		$checkResult = $checkQb->executeQuery();
		$exists = $checkResult->fetch();
		$checkResult->closeCursor();

		$timestamp = time();

		if ($exists) {
			// Update existing stats
			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('forum_user_stats')
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
			// Create new stats
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('forum_user_stats')
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
				$this->logger->warning('Failed to create user stats, attempting update', [
					'userId' => $userId,
					'exception' => $e->getMessage(),
				]);

				$updateQb = $this->db->getQueryBuilder();
				$updateQb->update('forum_user_stats')
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
}
