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
 * @template-extends QBMapper<ForumUser>
 */
class ForumUserMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_users'), ForumUser::class);
	}

	/**
	 * Find forum user by user ID
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(string $userId): ForumUser {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find all forum users
	 *
	 * @return array<ForumUser>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('post_count', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Find forum users by multiple user IDs
	 *
	 * @param array<string> $userIds
	 * @return array<ForumUser>
	 */
	public function findByUserIds(array $userIds): array {
		if (empty($userIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));
		return $this->findEntities($qb);
	}

	/**
	 * Create or update forum user (upsert pattern)
	 * This is used when we need to ensure a forum user record exists
	 */
	public function createOrUpdate(string $userId): ForumUser {
		try {
			return $this->find($userId);
		} catch (DoesNotExistException $e) {
			$user = new ForumUser();
			$user->setUserId($userId);
			$user->setPostCount(0);
			$user->setThreadCount(0);
			$user->setCreatedAt(time());
			$user->setUpdatedAt(time());
			/** @var ForumUser */
			return $this->insert($user);
		}
	}

	/**
	 * Increment post count for a user
	 * Auto-creates record if user doesn't exist
	 */
	public function incrementPostCount(string $userId, int $amount = 1): void {
		$user = $this->createOrUpdate($userId);
		$user->setPostCount($user->getPostCount() + $amount);
		$user->setLastPostAt(time());
		$user->setUpdatedAt(time());
		$this->update($user);
	}

	/**
	 * Increment thread count for a user
	 * Auto-creates record if user doesn't exist
	 */
	public function incrementThreadCount(string $userId, int $amount = 1): void {
		$user = $this->createOrUpdate($userId);
		$user->setThreadCount($user->getThreadCount() + $amount);
		$user->setUpdatedAt(time());
		$this->update($user);
	}

	/**
	 * Decrement post count for a user
	 */
	public function decrementPostCount(string $userId, int $amount = 1): void {
		try {
			$user = $this->find($userId);
			$user->setPostCount(max(0, $user->getPostCount() - $amount));
			$user->setUpdatedAt(time());
			$this->update($user);
		} catch (DoesNotExistException $e) {
			// User record doesn't exist, nothing to decrement
		}
	}

	/**
	 * Decrement thread count for a user
	 */
	public function decrementThreadCount(string $userId, int $amount = 1): void {
		try {
			$user = $this->find($userId);
			$user->setThreadCount(max(0, $user->getThreadCount() - $amount));
			$user->setUpdatedAt(time());
			$this->update($user);
		} catch (DoesNotExistException $e) {
			// User record doesn't exist, nothing to decrement
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
			$user = $this->find($userId);
			$user->setDeletedAt(time());
			$user->setUpdatedAt(time());
			$this->update($user);
		} catch (DoesNotExistException $e) {
			// User has no record, create one marking them as deleted
			$user = new ForumUser();
			$user->setUserId($userId);
			$user->setPostCount(0);
			$user->setThreadCount(0);
			$user->setDeletedAt(time());
			$user->setCreatedAt(time());
			$user->setUpdatedAt(time());
			$this->insert($user);
		}
	}
}
