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
 * @template-extends QBMapper<Post>
 */
class PostMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_posts'), Post::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Post {
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
	public function findBySlug(string $slug): Post {
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
	 * @return array<Post>
	 */
	public function findByThreadId(int $threadId, int $limit = 50, int $offset = 0): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->isNull('deleted_at')
			)
			->orderBy('created_at', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * @return array<Post>
	 */
	public function findByAuthorId(string $authorId, int $limit = 50, int $offset = 0, bool $excludeFirstPosts = false): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'forum_threads', 't', $qb->expr()->eq('p.thread_id', 't.id'))
			->where(
				$qb->expr()->eq('p.author_id', $qb->createNamedParameter($authorId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNull('p.deleted_at')
			)
			->andWhere(
				$qb->expr()->isNull('t.deleted_at')
			);

		if ($excludeFirstPosts) {
			$qb->andWhere(
				$qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			);
		}

		$qb->orderBy('p.created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * @return array<Post>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'forum_threads', 't', $qb->expr()->eq('p.thread_id', 't.id'))
			->where(
				$qb->expr()->isNull('p.deleted_at')
			)
			->andWhere(
				$qb->expr()->isNull('t.deleted_at')
			)
			->orderBy('p.created_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Count all posts
	 */
	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'forum_threads', 't', $qb->expr()->eq('p.thread_id', 't.id'))
			->where(
				$qb->expr()->isNull('p.deleted_at')
			)
			->andWhere(
				$qb->expr()->isNull('t.deleted_at')
			);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Count posts created since a timestamp
	 */
	public function countSince(int $timestamp): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'forum_threads', 't', $qb->expr()->eq('p.thread_id', 't.id'))
			->where($qb->expr()->gte('p.created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)))
			->andWhere(
				$qb->expr()->isNull('p.deleted_at')
			)
			->andWhere(
				$qb->expr()->isNull('t.deleted_at')
			)
			->andWhere(
				$qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Find the latest non-deleted post in a thread, excluding a specific post ID
	 *
	 * @param int $threadId Thread ID
	 * @param int|null $excludePostId Post ID to exclude (typically the one being deleted)
	 * @return Post|null Latest post or null if no posts found
	 */
	public function findLatestByThreadId(int $threadId, ?int $excludePostId = null): ?Post {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));

		if ($excludePostId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludePostId, IQueryBuilder::PARAM_INT)));
		}

		$qb->orderBy('created_at', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Count unread posts in a thread after a specific post ID
	 *
	 * @param int $threadId Thread ID
	 * @param int $afterPostId Post ID to count after (0 to count all posts)
	 * @return int Number of posts after the given post ID
	 */
	public function countUnreadInThread(int $threadId, int $afterPostId = 0): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));

		if ($afterPostId > 0) {
			$qb->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($afterPostId, IQueryBuilder::PARAM_INT)));
		}

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return (int)($row['count'] ?? 0);
	}

	/**
	 * Search posts by content (replies only, excluding first posts)
	 *
	 * @param IQueryBuilder $qb QueryBuilder instance (with parameters already bound)
	 * @param \OCP\DB\QueryBuilder\ICompositeExpression $whereConditions WHERE expression from QueryParser
	 * @param array<int> $categoryIds Category IDs to search in
	 * @param int $limit Maximum results
	 * @param int $offset Results offset
	 * @return array<Post>
	 */
	public function search(IQueryBuilder $qb, \OCP\DB\QueryBuilder\ICompositeExpression $whereConditions, array $categoryIds, int $limit = 50, int $offset = 0): array {

		// Select posts with JOIN to threads for category filtering
		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'forum_threads', 't', $qb->expr()->eq('p.thread_id', 't.id'))
			->where($qb->expr()->in('t.category_id', $qb->createNamedParameter($categoryIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('p.deleted_at'))
			->andWhere($qb->expr()->isNull('t.deleted_at'))
			->andWhere($qb->expr()->eq('t.is_hidden', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($whereConditions)
			->orderBy('p.created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}
}
