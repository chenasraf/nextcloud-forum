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
 * @template-extends QBMapper<ReadMarker>
 */
class ReadMarkerMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_read_markers'), ReadMarker::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): ReadMarker {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findByUserAndThread(string $userId, int $threadId): ReadMarker {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @return array<ReadMarker>
	 */
	public function findByUserId(string $userId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Find read markers for a user across multiple threads
	 *
	 * @param string $userId
	 * @param array<int> $threadIds
	 * @return array<ReadMarker>
	 */
	public function findByUserAndThreads(string $userId, array $threadIds): array {
		if (empty($threadIds)) {
			return [];
		}

		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->in('thread_id', $qb->createNamedParameter($threadIds, IQueryBuilder::PARAM_INT_ARRAY))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Create or update a read marker
	 */
	public function createOrUpdate(string $userId, int $threadId, int $lastReadPostId): ReadMarker {
		try {
			// Try to find existing marker
			$marker = $this->findByUserAndThread($userId, $threadId);

			// Update existing marker
			$marker->setLastReadPostId($lastReadPostId);
			$marker->setReadAt(time());
			return $this->update($marker);
		} catch (DoesNotExistException $e) {
			// Create new marker
			$marker = new ReadMarker();
			$marker->setUserId($userId);
			$marker->setThreadId($threadId);
			$marker->setLastReadPostId($lastReadPostId);
			$marker->setReadAt(time());
			return $this->insert($marker);
		}
	}

	/**
	 * @return array<ReadMarker>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
