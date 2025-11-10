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
 * @template-extends QBMapper<Thread>
 */
class ThreadMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_threads'), Thread::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Thread {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->isNull('deleted_at')
			);
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findBySlug(string $slug): Thread {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('slug', $qb->createNamedParameter($slug, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNull('deleted_at')
			);
		return $this->findEntity($qb);
	}

	/**
	 * @return array<Thread>
	 */
	public function findByCategoryId(int $categoryId, int $limit = 50, int $offset = 0): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			)
			->andWhere(
				$qb->expr()->isNull('deleted_at')
			)
			->orderBy('is_pinned', 'DESC')
			->addOrderBy('updated_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * @return array<Thread>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->isNull('deleted_at')
			)
			->orderBy('is_pinned', 'DESC')
			->addOrderBy('updated_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Count all threads
	 */
	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where(
				$qb->expr()->isNull('deleted_at')
			);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Count threads created since a timestamp
	 */
	public function countSince(int $timestamp): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->gte('created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)))
			->andWhere(
				$qb->expr()->isNull('deleted_at')
			);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Move all threads from one category to another
	 */
	public function moveToCategoryId(int $fromCategoryId, int $toCategoryId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('category_id', $qb->createNamedParameter($toCategoryId, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($fromCategoryId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Soft delete all threads in a category (set is_hidden = true)
	 */
	public function softDeleteByCategoryId(int $categoryId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_hidden', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Count threads in a category
	 */
	public function countByCategoryId(int $categoryId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_hidden', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere(
				$qb->expr()->isNull('deleted_at')
			);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Search threads by title and first post content
	 *
	 * @param IQueryBuilder $qb QueryBuilder instance (with parameters already bound)
	 * @param \OCP\DB\QueryBuilder\ICompositeExpression $titleConditions WHERE expression for title field
	 * @param \OCP\DB\QueryBuilder\ICompositeExpression $contentConditions WHERE expression for content field
	 * @param array<int> $categoryIds Category IDs to search in
	 * @param int $limit Maximum results
	 * @param int $offset Results offset
	 * @return array<Thread>
	 */
	public function search(IQueryBuilder $qb, \OCP\DB\QueryBuilder\ICompositeExpression $titleConditions, \OCP\DB\QueryBuilder\ICompositeExpression $contentConditions, array $categoryIds, int $limit = 50, int $offset = 0): array {

		// Select threads with LEFT JOIN to first post
		$qb->select('t.*')
			->from($this->getTableName(), 't')
			->leftJoin('t', 'forum_posts', 'p', $qb->expr()->andX(
				$qb->expr()->eq('t.id', 'p.thread_id'),
				$qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			))
			->where($qb->expr()->in('t.category_id', $qb->createNamedParameter($categoryIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('t.is_hidden', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('t.deleted_at'))
			->andWhere(
				// Search in title OR first post content
				$qb->expr()->orX(
					$titleConditions,
					$contentConditions
				)
			)
			->orderBy('t.is_pinned', 'DESC')
			->addOrderBy('t.updated_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}
}
