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
 * Mapper for polymorphic bookmarks that can reference different entity types
 *
 * @template-extends QBMapper<Bookmark>
 */
class BookmarkMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_bookmarks'), Bookmark::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Bookmark {
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
	 * Find a bookmark by user, entity type and entity ID
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findByUserAndEntity(string $userId, string $entityType, int $entityId): Bookmark {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_id', $qb->createNamedParameter($entityId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Check if a user has bookmarked an entity
	 */
	public function isBookmarked(string $userId, string $entityType, int $entityId): bool {
		try {
			$this->findByUserAndEntity($userId, $entityType, $entityId);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * Check if a user has bookmarked a thread (convenience method)
	 */
	public function isThreadBookmarked(string $userId, int $threadId): bool {
		return $this->isBookmarked($userId, Bookmark::ENTITY_TYPE_THREAD, $threadId);
	}

	/**
	 * Get all bookmarked entity IDs for a user and entity type (for batch checking)
	 *
	 * @return array<int> Array of entity IDs
	 */
	public function getBookmarkedEntityIds(string $userId, string $entityType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('entity_id')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType, IQueryBuilder::PARAM_STR))
			);

		$result = $qb->executeQuery();
		$entityIds = [];
		while ($row = $result->fetch()) {
			$entityIds[] = (int)$row['entity_id'];
		}
		$result->closeCursor();
		return $entityIds;
	}

	/**
	 * Get all bookmarked thread IDs for a user (convenience method)
	 *
	 * @return array<int> Array of thread IDs
	 */
	public function getBookmarkedThreadIds(string $userId): array {
		return $this->getBookmarkedEntityIds($userId, Bookmark::ENTITY_TYPE_THREAD);
	}

	/**
	 * Get all bookmarks for a user and entity type, ordered by most recently bookmarked first
	 *
	 * @return array<Bookmark>
	 */
	public function findByUserIdAndType(string $userId, string $entityType, int $limit = 50, int $offset = 0): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType, IQueryBuilder::PARAM_STR))
			)
			->orderBy('created_at', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * Get all thread bookmarks for a user (convenience method)
	 *
	 * @return array<Bookmark>
	 */
	public function findThreadBookmarksByUserId(string $userId, int $limit = 50, int $offset = 0): array {
		return $this->findByUserIdAndType($userId, Bookmark::ENTITY_TYPE_THREAD, $limit, $offset);
	}

	/**
	 * Count bookmarks for a user and entity type
	 */
	public function countByUserIdAndType(string $userId, string $entityType): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType, IQueryBuilder::PARAM_STR))
			);

		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?? 0);
		$result->closeCursor();
		return $count;
	}

	/**
	 * Count thread bookmarks for a user (convenience method)
	 */
	public function countThreadBookmarksByUserId(string $userId): int {
		return $this->countByUserIdAndType($userId, Bookmark::ENTITY_TYPE_THREAD);
	}

	/**
	 * Create a bookmark for an entity
	 */
	public function bookmark(string $userId, string $entityType, int $entityId): Bookmark {
		// Check if already bookmarked
		if ($this->isBookmarked($userId, $entityType, $entityId)) {
			return $this->findByUserAndEntity($userId, $entityType, $entityId);
		}

		// Create new bookmark
		$bookmark = new Bookmark();
		$bookmark->setUserId($userId);
		$bookmark->setEntityType($entityType);
		$bookmark->setEntityId($entityId);
		$bookmark->setCreatedAt(time());
		return $this->insert($bookmark);
	}

	/**
	 * Bookmark a thread (convenience method)
	 */
	public function bookmarkThread(string $userId, int $threadId): Bookmark {
		return $this->bookmark($userId, Bookmark::ENTITY_TYPE_THREAD, $threadId);
	}

	/**
	 * Remove a bookmark for an entity
	 */
	public function unbookmark(string $userId, string $entityType, int $entityId): void {
		try {
			$bookmark = $this->findByUserAndEntity($userId, $entityType, $entityId);
			$this->delete($bookmark);
		} catch (DoesNotExistException $e) {
			// Already not bookmarked, nothing to do
		}
	}

	/**
	 * Remove a thread bookmark (convenience method)
	 */
	public function unbookmarkThread(string $userId, int $threadId): void {
		$this->unbookmark($userId, Bookmark::ENTITY_TYPE_THREAD, $threadId);
	}

	/**
	 * @return array<Bookmark>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
