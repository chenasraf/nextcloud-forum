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
 * Mapper for polymorphic drafts that can be used for threads or posts
 *
 * @template-extends QBMapper<Draft>
 */
class DraftMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_drafts'), Draft::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Draft {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find a draft by user, entity type and parent ID
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findByUserAndParent(string $userId, string $entityType, int $parentId): Draft {
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
				$qb->expr()->eq('parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find a thread draft by user and category (convenience method)
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findThreadDraft(string $userId, int $categoryId): Draft {
		return $this->findByUserAndParent($userId, Draft::ENTITY_TYPE_THREAD, $categoryId);
	}

	/**
	 * Check if a user has a draft for a specific entity type and parent
	 */
	public function hasDraft(string $userId, string $entityType, int $parentId): bool {
		try {
			$this->findByUserAndParent($userId, $entityType, $parentId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		}
	}

	/**
	 * Check if a user has a thread draft for a category (convenience method)
	 */
	public function hasThreadDraft(string $userId, int $categoryId): bool {
		return $this->hasDraft($userId, Draft::ENTITY_TYPE_THREAD, $categoryId);
	}

	/**
	 * Get all drafts for a user by entity type
	 *
	 * @return array<Draft>
	 */
	public function findByUserAndType(string $userId, string $entityType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType, IQueryBuilder::PARAM_STR))
			)
			->orderBy('updated_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Get all thread drafts for a user (convenience method)
	 *
	 * @return array<Draft>
	 */
	public function findThreadDrafts(string $userId): array {
		return $this->findByUserAndType($userId, Draft::ENTITY_TYPE_THREAD);
	}

	/**
	 * Create or update a draft (upsert behavior)
	 */
	public function saveDraft(string $userId, string $entityType, int $parentId, ?string $title, string $content): Draft {
		$now = time();

		try {
			// Try to find existing draft
			$draft = $this->findByUserAndParent($userId, $entityType, $parentId);
			// Update existing draft
			$draft->setTitle($title);
			$draft->setContent($content);
			$draft->setUpdatedAt($now);
			return $this->update($draft);
		} catch (DoesNotExistException) {
			// Create new draft
			$draft = new Draft();
			$draft->setUserId($userId);
			$draft->setEntityType($entityType);
			$draft->setParentId($parentId);
			$draft->setTitle($title);
			$draft->setContent($content);
			$draft->setCreatedAt($now);
			$draft->setUpdatedAt($now);
			return $this->insert($draft);
		}
	}

	/**
	 * Save a thread draft (convenience method)
	 */
	public function saveThreadDraft(string $userId, int $categoryId, ?string $title, string $content): Draft {
		return $this->saveDraft($userId, Draft::ENTITY_TYPE_THREAD, $categoryId, $title, $content);
	}

	/**
	 * Delete a draft by user, entity type and parent
	 */
	public function deleteDraft(string $userId, string $entityType, int $parentId): void {
		try {
			$draft = $this->findByUserAndParent($userId, $entityType, $parentId);
			$this->delete($draft);
		} catch (DoesNotExistException) {
			// Already deleted or doesn't exist, nothing to do
		}
	}

	/**
	 * Delete a thread draft (convenience method)
	 */
	public function deleteThreadDraft(string $userId, int $categoryId): void {
		$this->deleteDraft($userId, Draft::ENTITY_TYPE_THREAD, $categoryId);
	}

	/**
	 * Count drafts for a user by entity type
	 */
	public function countByUserAndType(string $userId, string $entityType): int {
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
	 * Count thread drafts for a user (convenience method)
	 */
	public function countThreadDrafts(string $userId): int {
		return $this->countByUserAndType($userId, Draft::ENTITY_TYPE_THREAD);
	}

	/**
	 * @return array<Draft>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
