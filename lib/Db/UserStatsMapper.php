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
		parent::__construct($db, Application::tableName('user_stats'), UserStats::class);
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
	 * Get top contributors by post count
	 *
	 * @return array<array{userId: string, postCount: int}>
	 */
	public function getTopContributors(int $limit = 10): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'post_count')
			->from($this->getTableName())
			->where($qb->expr()->isNull('deleted_at'))
			->orderBy('post_count', 'DESC')
			->setMaxResults($limit);

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(fn ($row) => [
			'userId' => $row['user_id'],
			'postCount' => (int)$row['post_count'],
		], $rows);
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
