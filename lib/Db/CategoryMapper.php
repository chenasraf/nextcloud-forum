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
 * @template-extends QBMapper<Category>
 */
class CategoryMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_categories'), Category::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Category {
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
	public function findBySlug(string $slug): Category {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('slug', $qb->createNamedParameter($slug, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}


	/**
	 * @return array<Category>
	 */
	public function findByHeaderId(int $headerId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('header_id', $qb->createNamedParameter($headerId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('sort_order', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @return array<Category>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('sort_order', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Count all categories
	 */
	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName());
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Find top categories by thread count
	 *
	 * @param array<int> $categoryIds Category IDs to filter by (already permission-filtered)
	 * @param int $limit Maximum results
	 * @return array<Category>
	 */
	public function findTopByThreadCount(array $categoryIds, int $limit = 7): array {
		if (empty($categoryIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($categoryIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('thread_count', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	/**
	 * Find direct children of a category
	 *
	 * @param int $parentId Parent category ID
	 * @return array<Category>
	 */
	public function findByParentId(int $parentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('sort_order', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Find all descendants of a category (iterative breadth-first)
	 *
	 * @param int $categoryId Root category ID
	 * @return array<Category> All descendants (not including the root)
	 */
	public function findChildren(int $categoryId): array {
		$allChildren = [];
		$queue = [$categoryId];

		while (!empty($queue)) {
			$currentId = array_shift($queue);
			$children = $this->findByParentId($currentId);
			foreach ($children as $child) {
				$allChildren[] = $child;
				$queue[] = $child->getId();
			}
		}

		return $allChildren;
	}

	/**
	 * Move all categories from one header to another
	 *
	 * @param int $fromHeaderId Source header ID
	 * @param int $toHeaderId Target header ID
	 * @return int Number of categories moved
	 */
	public function moveToHeaderId(int $fromHeaderId, int $toHeaderId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('header_id', $qb->createNamedParameter($toHeaderId, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('header_id', $qb->createNamedParameter($fromHeaderId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
