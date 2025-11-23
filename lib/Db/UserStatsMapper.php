<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use OCA\Forum\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<UserStats>
 */
class UserStatsMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_user_stats'), UserStats::class);
	}

	/**
	 * Find user stats by user ID
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(string $userId): UserStats {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find all user stats
	 *
	 * @return array<UserStats>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('post_count', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Create or update user stats (upsert pattern)
	 * This is used when we need to ensure stats exist for a user
	 */
	public function createOrUpdate(string $userId): UserStats {
		try {
			return $this->find($userId);
		} catch (DoesNotExistException $e) {
			$stats = new UserStats();
			$stats->setUserId($userId);
			$stats->setPostCount(0);
			$stats->setThreadCount(0);
			$stats->setCreatedAt(time());
			$stats->setUpdatedAt(time());
			/** @var UserStats */
			return $this->insert($stats);
		}
	}

	/**
	 * Increment post count for a user
	 * Auto-creates stats if user doesn't exist
	 */
	public function incrementPostCount(string $userId, int $amount = 1): void {
		$stats = $this->createOrUpdate($userId);
		$stats->setPostCount($stats->getPostCount() + $amount);
		$stats->setLastPostAt(time());
		$stats->setUpdatedAt(time());
		$this->update($stats);
	}

	/**
	 * Increment thread count for a user
	 * Auto-creates stats if user doesn't exist
	 */
	public function incrementThreadCount(string $userId, int $amount = 1): void {
		$stats = $this->createOrUpdate($userId);
		$stats->setThreadCount($stats->getThreadCount() + $amount);
		$stats->setUpdatedAt(time());
		$this->update($stats);
	}

	/**
	 * Decrement post count for a user
	 */
	public function decrementPostCount(string $userId, int $amount = 1): void {
		try {
			$stats = $this->find($userId);
			$stats->setPostCount(max(0, $stats->getPostCount() - $amount));
			$stats->setUpdatedAt(time());
			$this->update($stats);
		} catch (DoesNotExistException $e) {
			// User stats don't exist, nothing to decrement
		}
	}

	/**
	 * Decrement thread count for a user
	 */
	public function decrementThreadCount(string $userId, int $amount = 1): void {
		try {
			$stats = $this->find($userId);
			$stats->setThreadCount(max(0, $stats->getThreadCount() - $amount));
			$stats->setUpdatedAt(time());
			$this->update($stats);
		} catch (DoesNotExistException $e) {
			// User stats don't exist, nothing to decrement
		}
	}

	/**
	 * Get top contributors by total activity (posts + threads)
	 *
	 * @return array<array{userId: string, postCount: int, threadCount: int}>
	 */
	public function getTopContributors(int $limit = 10): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'post_count', 'thread_count')
			->from($this->getTableName())
			->where($qb->expr()->isNull('deleted_at'))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->gt('post_count', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
					$qb->expr()->gt('thread_count', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				)
			);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		// Calculate total and sort in PHP
		$contributors = array_map(fn ($row) => [
			'userId' => $row['user_id'],
			'postCount' => (int)$row['post_count'],
			'threadCount' => (int)$row['thread_count'],
			'total' => (int)$row['post_count'] + (int)$row['thread_count'],
		], $rows);

		// Sort by total descending, then by thread count descending (when totals are equal)
		usort($contributors, fn ($a, $b)
			=> $b['total'] <=> $a['total'] ?: $b['threadCount'] <=> $a['threadCount']
		);

		// Return top N (remove the total field as it was just for sorting)
		return array_slice(array_map(fn ($c) => [
			'userId' => $c['userId'],
			'postCount' => $c['postCount'],
			'threadCount' => $c['threadCount'],
		], $contributors), 0, $limit);
	}

	/**
	 * Get top contributors for a specific time period by counting posts/threads directly
	 *
	 * @return array<array{userId: string, postCount: int, threadCount: int}>
	 */
	public function getTopContributorsSince(int $timestamp, int $limit = 10): array {
		// Count posts per user since timestamp (excluding first posts which are counted as threads)
		$postsQb = $this->db->getQueryBuilder();
		$postsQb->select('author_id')
			->selectAlias($postsQb->func()->count('*'), 'count')
			->from(Application::tableName('forum_posts'))
			->where($postsQb->expr()->gte('created_at', $postsQb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)))
			->andWhere($postsQb->expr()->isNull('deleted_at'))
			->andWhere($postsQb->expr()->eq('is_first_post', $postsQb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->groupBy('author_id');

		$postsResult = $postsQb->executeQuery();
		$postsRows = $postsResult->fetchAll();
		$postsResult->closeCursor();

		$postsByUser = [];
		foreach ($postsRows as $row) {
			$postsByUser[$row['author_id']] = (int)$row['count'];
		}

		// Count threads per user since timestamp
		$threadsQb = $this->db->getQueryBuilder();
		$threadsQb->select('author_id')
			->selectAlias($threadsQb->func()->count('*'), 'count')
			->from(Application::tableName('forum_threads'))
			->where($threadsQb->expr()->gte('created_at', $threadsQb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)))
			->andWhere($threadsQb->expr()->isNull('deleted_at'))
			->groupBy('author_id');

		$threadsResult = $threadsQb->executeQuery();
		$threadsRows = $threadsResult->fetchAll();
		$threadsResult->closeCursor();

		$threadsByUser = [];
		foreach ($threadsRows as $row) {
			$threadsByUser[$row['author_id']] = (int)$row['count'];
		}

		// Combine and calculate totals
		$allUserIds = array_unique(array_merge(array_keys($postsByUser), array_keys($threadsByUser)));
		$contributors = [];

		foreach ($allUserIds as $userId) {
			$postCount = $postsByUser[$userId] ?? 0;
			$threadCount = $threadsByUser[$userId] ?? 0;
			$total = $postCount + $threadCount;

			if ($total > 0) {
				$contributors[] = [
					'userId' => $userId,
					'postCount' => $postCount,
					'threadCount' => $threadCount,
					'total' => $total,
				];
			}
		}

		// Sort by total descending, then by thread count descending (when totals are equal)
		usort($contributors, fn ($a, $b)
			=> $b['total'] <=> $a['total'] ?: $b['threadCount'] <=> $a['threadCount']
		);

		// Return top N (remove the total field as it was just for sorting)
		return array_slice(array_map(fn ($c) => [
			'userId' => $c['userId'],
			'postCount' => $c['postCount'],
			'threadCount' => $c['threadCount'],
		], $contributors), 0, $limit);
	}

	/**
	 * Count all users (excluding deleted)
	 */
	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->isNull('deleted_at'));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Count users who posted since a timestamp
	 */
	public function countSince(int $timestamp): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->gte('created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Mark a user as deleted
	 */
	public function markDeleted(string $userId): void {
		try {
			$stats = $this->find($userId);
			$stats->setDeletedAt(time());
			$stats->setUpdatedAt(time());
			$this->update($stats);
		} catch (DoesNotExistException $e) {
			// User has no stats, create a record marking them as deleted
			$stats = new UserStats();
			$stats->setUserId($userId);
			$stats->setPostCount(0);
			$stats->setThreadCount(0);
			$stats->setDeletedAt(time());
			$stats->setCreatedAt(time());
			$stats->setUpdatedAt(time());
			$this->insert($stats);
		}
	}
}
