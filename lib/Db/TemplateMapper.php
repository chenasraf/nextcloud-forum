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
 * @template-extends QBMapper<Template>
 */
class TemplateMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_templates'), Template::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Template {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find all templates for a user, optionally filtered by visibility
	 *
	 * @param string $userId
	 * @param string|null $visibility Filter by visibility (threads, replies, both, neither)
	 * @return array<Template>
	 */
	public function findByUserId(string $userId, ?string $visibility = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		if ($visibility !== null) {
			$qb->andWhere(
				$qb->expr()->in('visibility', $qb->createNamedParameter(
					[$visibility, Template::VISIBILITY_BOTH],
					IQueryBuilder::PARAM_STR_ARRAY
				))
			);
		}

		$qb->orderBy('sort_order', 'ASC')
			->addOrderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Count templates for a user
	 */
	public function countByUserId(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?? 0);
		$result->closeCursor();
		return $count;
	}
}
