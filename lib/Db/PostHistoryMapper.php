<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use OCA\Forum\AppInfo\Application;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<PostHistory>
 */
class PostHistoryMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_post_history'), PostHistory::class);
	}

	/**
	 * Find all history entries for a post, ordered by edited_at descending (newest first)
	 *
	 * @param int $postId Post ID
	 * @return array<PostHistory>
	 */
	public function findByPostId(int $postId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('post_id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('edited_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Count history entries for a post
	 *
	 * @param int $postId Post ID
	 * @return int Number of history entries
	 */
	public function countByPostId(int $postId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('post_id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT))
			);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Delete all history entries for a post
	 *
	 * @param int $postId Post ID
	 */
	public function deleteByPostId(int $postId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('post_id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}
}
