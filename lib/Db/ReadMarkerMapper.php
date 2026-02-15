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
				$qb->expr()->eq('marker_type', $qb->createNamedParameter(ReadMarker::TYPE_THREAD, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT))
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
			)
			->andWhere(
				$qb->expr()->eq('marker_type', $qb->createNamedParameter(ReadMarker::TYPE_THREAD, IQueryBuilder::PARAM_STR))
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
				$qb->expr()->eq('marker_type', $qb->createNamedParameter(ReadMarker::TYPE_THREAD, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->in('entity_id', $qb->createNamedParameter($threadIds, IQueryBuilder::PARAM_INT_ARRAY))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Create or update a read marker
	 * Only updates if the new lastReadPostId is greater than the current one
	 * (prevents regressing the read marker when navigating to earlier pages)
	 */
	public function createOrUpdate(string $userId, int $threadId, int $lastReadPostId): ReadMarker {
		try {
			// Try to find existing marker
			$marker = $this->findByUserAndThread($userId, $threadId);

			// Only update if the new post ID is greater than the current one
			if ($lastReadPostId > $marker->getLastReadPostId()) {
				$marker->setLastReadPostId($lastReadPostId);
				$marker->setReadAt(time());
				return $this->update($marker);
			}

			// Return existing marker without changes
			return $marker;
		} catch (DoesNotExistException $e) {
			// Create new marker
			$marker = new ReadMarker();
			$marker->setUserId($userId);
			$marker->setEntityId($threadId);
			$marker->setMarkerType(ReadMarker::TYPE_THREAD);
			$marker->setLastReadPostId($lastReadPostId);
			$marker->setReadAt(time());
			return $this->insert($marker);
		}
	}

	/**
	 * Find all category read markers for a user
	 *
	 * @return array<ReadMarker>
	 */
	public function findCategoryMarkersByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('marker_type', $qb->createNamedParameter(ReadMarker::TYPE_CATEGORY, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Create or update a category read marker
	 */
	public function createOrUpdateCategoryMarker(string $userId, int $categoryId): ReadMarker {
		try {
			// Try to find existing marker
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('marker_type', $qb->createNamedParameter(ReadMarker::TYPE_CATEGORY, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('entity_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
				);
			$marker = $this->findEntity($qb);

			// Always update the timestamp
			$marker->setReadAt(time());
			return $this->update($marker);
		} catch (DoesNotExistException $e) {
			// Create new marker
			$marker = new ReadMarker();
			$marker->setUserId($userId);
			$marker->setEntityId($categoryId);
			$marker->setMarkerType(ReadMarker::TYPE_CATEGORY);
			$marker->setLastReadPostId(null);
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
