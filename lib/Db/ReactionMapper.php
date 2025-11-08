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
 * @template-extends QBMapper<Reaction>
 */
class ReactionMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_reactions'), Reaction::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Reaction {
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
	 * @return array<Reaction>
	 */
	public function findByPostId(int $postId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('post_id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

	/**
	 * @return array<Reaction>
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
	 * @return array<Reaction>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * Find reactions for multiple posts at once (for performance)
	 * @param array<int> $postIds Array of post IDs
	 * @return array<Reaction>
	 */
	public function findByPostIds(array $postIds): array {
		if (empty($postIds)) {
			return [];
		}

		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('post_id', $qb->createNamedParameter($postIds, IQueryBuilder::PARAM_INT_ARRAY))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Find a reaction by post ID, user ID, and reaction type
	 * @throws DoesNotExistException
	 */
	public function findByPostUserAndType(int $postId, string $userId, string $reactionType): Reaction {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('post_id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('reaction_type', $qb->createNamedParameter($reactionType, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}
}
