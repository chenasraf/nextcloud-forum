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
	 * Find posts by multiple IDs
	 *
	 * @param array<int> $ids
	 * @return array<Post>
	 */
	public function findByIds(array $ids): array {
		if (empty($ids)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->isNull('deleted_at'));
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
	 * Count all replies (posts excluding first posts)
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
			)
			->andWhere(
				$qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			);
		$result = $qb->executeQuery();
		/** @var array{count: int|string}|false $row */
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? 0 : (int)$row['count'];
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
		/** @var array{count: int|string}|false $row */
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? 0 : (int)$row['count'];
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
		/** @var array{count: int|string}|false $row */
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? 0 : (int)$row['count'];
	}

	/**
	 * Find the first post in a thread
	 *
	 * @param int $threadId Thread ID
	 * @return Post|null First post or null if not found
	 */
	public function findFirstPostByThreadId(int $threadId): ?Post {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_first_post', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Find the top-level replies of a thread with pagination.
	 *
	 * A soft-deleted top-level reply is still included while it has visible
	 * descendants, so the thread can render it as a placeholder that keeps its
	 * subtree in place.
	 *
	 * @return array<Post>
	 */
	public function findTopLevelReplies(int $threadId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName());
		$this->applyTopLevelFilter($qb, $threadId);
		$qb->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Count the top-level replies of a thread, as listed by findTopLevelReplies()
	 */
	public function countTopLevelReplies(int $threadId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName());
		$this->applyTopLevelFilter($qb, $threadId);
		$result = $qb->executeQuery();
		/** @var array{count: int|string}|false $row */
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? 0 : (int)$row['count'];
	}

	/**
	 * Find every nested reply below the given top-level replies, including
	 * soft-deleted ones, ordered oldest first
	 *
	 * @param array<int> $rootIds Top-level reply IDs
	 * @return array<Post>
	 */
	public function findDescendantsByRootIds(array $rootIds): array {
		if (empty($rootIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('root_reply_id', $qb->createNamedParameter($rootIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Move the direct children of a post up to that post's parent, before the
	 * post is permanently deleted.
	 *
	 * When the post is a top-level reply its children become top-level replies
	 * themselves, and each of them becomes the root of its own subtree.
	 */
	public function reparentChildren(Post $post): void {
		$parentId = $post->getParentPostId();

		if ($parentId !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->update($this->getTableName())
				->set('parent_post_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('parent_post_id', $qb->createNamedParameter($post->getId(), IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
			return;
		}

		$subtree = $this->findDescendantsByRootIds([$post->getId()]);
		if (empty($subtree)) {
			return;
		}

		$parentById = [];
		foreach ($subtree as $descendant) {
			$parentById[$descendant->getId()] = $descendant->getParentPostId();
		}

		foreach ($subtree as $descendant) {
			// Walk up to the child of the deleted post; that child is the new root
			$newRoot = $descendant->getId();
			$parentId = $parentById[$newRoot] ?? null;
			while ($parentId !== null && $parentId !== $post->getId() && array_key_exists($parentId, $parentById)) {
				$newRoot = $parentId;
				$parentId = $parentById[$newRoot];
			}

			$isNewRoot = $newRoot === $descendant->getId();
			$qb = $this->db->getQueryBuilder();
			$qb->update($this->getTableName())
				->set('root_reply_id', $isNewRoot
					? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
					: $qb->createNamedParameter($newRoot, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($descendant->getId(), IQueryBuilder::PARAM_INT)));
			if ($isNewRoot) {
				$qb->set('parent_post_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL));
			}
			$qb->executeStatement();
		}
	}

	/**
	 * Restrict a query to the top-level replies of a thread, keeping deleted
	 * ones that still have visible descendants
	 */
	private function applyTopLevelFilter(IQueryBuilder $qb, int $threadId): void {
		$rootsWithLiveDescendants = $this->findRootIdsWithLiveDescendants($threadId);

		$visible = $qb->expr()->isNull('deleted_at');
		if (!empty($rootsWithLiveDescendants)) {
			$visible = $qb->expr()->orX(
				$visible,
				$qb->expr()->in('id', $qb->createNamedParameter($rootsWithLiveDescendants, IQueryBuilder::PARAM_INT_ARRAY)),
			);
		}

		$qb->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('parent_post_id'))
			->andWhere($visible);
	}

	/**
	 * @return array<int> IDs of top-level replies that have at least one non-deleted descendant
	 */
	private function findRootIdsWithLiveDescendants(int $threadId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('root_reply_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('root_reply_id'))
			->andWhere($qb->expr()->isNull('deleted_at'));
		$result = $qb->executeQuery();
		$ids = array_map(static fn (array $row): int => (int)$row['root_reply_id'], $result->fetchAll());
		$result->closeCursor();
		return $ids;
	}

	/**
	 * Find the oldest unread top-level reply in a thread. Nested replies are
	 * ignored so that opening a thread always lands on a top-level reply.
	 *
	 * @param int $threadId Thread ID
	 * @param int $afterPostId Post ID to look after (last read post ID)
	 * @return Post|null The oldest unread reply or null if all are read
	 */
	public function findOldestUnreadReply(int $threadId, int $afterPostId): ?Post {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('parent_post_id'))
			->andWhere($qb->expr()->gt('id', $qb->createNamedParameter($afterPostId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))
			->orderBy('created_at', 'ASC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Get the position (0-indexed) of a reply among the thread's top-level
	 * replies ordered by created_at ASC. A nested reply takes the position of
	 * the top-level reply it descends from, since it is shown on that page.
	 *
	 * @param int $threadId Thread ID
	 * @param int $postId Post ID to find position of
	 * @return int Position (0-indexed)
	 */
	public function getReplyPosition(int $threadId, int $postId): int {
		$targetId = $this->getRootReplyIdOf($postId);
		if ($targetId === null) {
			return 0;
		}

		$targetQb = $this->db->getQueryBuilder();
		$targetQb->select('created_at')
			->from($this->getTableName())
			->where($targetQb->expr()->eq('id', $targetQb->createNamedParameter($targetId, IQueryBuilder::PARAM_INT)));
		$targetResult = $targetQb->executeQuery();
		/** @var array{created_at: int|string}|false $targetRow */
		$targetRow = $targetResult->fetch();
		$targetResult->closeCursor();

		if (!$targetRow) {
			return 0;
		}

		$targetCreatedAt = (int)$targetRow['created_at'];

		// Count top-level replies listed before the target, matching the
		// created_at, id ordering of findTopLevelReplies()
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'position'))
			->from($this->getTableName());
		$this->applyTopLevelFilter($qb, $threadId);
		$qb->andWhere($qb->expr()->orX(
			$qb->expr()->lt('created_at', $qb->createNamedParameter($targetCreatedAt, IQueryBuilder::PARAM_INT)),
			$qb->expr()->andX(
				$qb->expr()->eq('created_at', $qb->createNamedParameter($targetCreatedAt, IQueryBuilder::PARAM_INT)),
				$qb->expr()->lt('id', $qb->createNamedParameter($targetId, IQueryBuilder::PARAM_INT)),
			),
		));

		$result = $qb->executeQuery();
		/** @var array{position: int|string}|false $row */
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? 0 : (int)$row['position'];
	}

	/**
	 * @return int|null ID of the top-level reply a post belongs to (the post
	 *                  itself when it is top-level), or null if it does not exist
	 */
	private function getRootReplyIdOf(int $postId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('root_reply_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		/** @var array{root_reply_id: int|string|null}|false $row */
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			return null;
		}
		return $row['root_reply_id'] !== null ? (int)$row['root_reply_id'] : $postId;
	}

	/**
	 * Find recent replies (non-first posts) in specified categories
	 *
	 * @param array<int> $categoryIds Category IDs to filter by
	 * @param int $limit Maximum results
	 * @return array<Post>
	 */
	public function findRecentReplies(array $categoryIds, int $limit = 7): array {
		if (empty($categoryIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->innerJoin('p', 'forum_threads', 't', $qb->expr()->eq('p.thread_id', 't.id'))
			->where($qb->expr()->in('t.category_id', $qb->createNamedParameter($categoryIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('p.deleted_at'))
			->andWhere($qb->expr()->isNull('t.deleted_at'))
			->andWhere($qb->expr()->eq('t.is_hidden', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->orderBy('p.created_at', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
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

	/**
	 * Find a post by ID including soft-deleted posts
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findIncludingDeleted(int $id): Post {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find soft-deleted replies (non-first-posts) with pagination, search, and sorting
	 *
	 * @return array<Post>
	 */
	public function findDeletedReplies(int $limit = 20, int $offset = 0, string $search = '', string $sort = 'newest'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNotNull('deleted_at'))
			->andWhere($qb->expr()->eq('is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));

		if ($search !== '') {
			$qb->andWhere($qb->expr()->iLike('content', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($search) . '%')));
		}

		$qb->orderBy('deleted_at', $sort === 'oldest' ? 'ASC' : 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	/**
	 * Count soft-deleted replies
	 */
	public function countDeletedReplies(string $search = ''): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->isNotNull('deleted_at'))
			->andWhere($qb->expr()->eq('is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));

		if ($search !== '') {
			$qb->andWhere($qb->expr()->iLike('content', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($search) . '%')));
		}

		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?? 0);
		$result->closeCursor();
		return $count;
	}

	/**
	 * Count all posts for a thread, including deleted posts
	 */
	public function countByThreadIdIncludingDeleted(int $threadId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)($result->fetchOne() ?? 0);
		$result->closeCursor();
		return $count;
	}

	/**
	 * Reassign all posts from one author to another
	 *
	 * @param string $fromAuthorId Current author ID (e.g., "guest:abc123")
	 * @param string $toAuthorId New author ID (e.g., "john")
	 * @return int Number of posts updated
	 */
	public function reassignAuthor(string $fromAuthorId, string $toAuthorId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('author_id', $qb->createNamedParameter($toAuthorId, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('author_id', $qb->createNamedParameter($fromAuthorId, IQueryBuilder::PARAM_STR)));
		return $qb->executeStatement();
	}

	/**
	 * Count posts by author (including deleted)
	 */
	public function countByAuthorId(string $authorId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->func()->count('*', 'total'),
		)
			->from($this->getTableName())
			->where($qb->expr()->eq('author_id', $qb->createNamedParameter($authorId, IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		/** @var array{total: int|string}|false $row */
		$row = $result->fetch();
		$result->closeCursor();

		// Count first posts (threads) vs replies separately
		$qb2 = $this->db->getQueryBuilder();
		$qb2->select($qb2->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb2->expr()->eq('author_id', $qb2->createNamedParameter($authorId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb2->expr()->eq('is_first_post', $qb2->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb2->expr()->isNull('deleted_at'));
		$result2 = $qb2->executeQuery();
		/** @var array{count: int|string}|false $row2 */
		$row2 = $result2->fetch();
		$result2->closeCursor();

		$qb3 = $this->db->getQueryBuilder();
		$qb3->select($qb3->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb3->expr()->eq('author_id', $qb3->createNamedParameter($authorId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb3->expr()->eq('is_first_post', $qb3->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb3->expr()->isNull('deleted_at'));
		$result3 = $qb3->executeQuery();
		/** @var array{count: int|string}|false $row3 */
		$row3 = $result3->fetch();
		$result3->closeCursor();

		return [
			'total' => $row === false ? 0 : (int)$row['total'],
			'threads' => $row2 === false ? 0 : (int)$row2['count'],
			'replies' => $row3 === false ? 0 : (int)$row3['count'],
		];
	}

	/**
	 * Get all post IDs for a thread, including deleted posts
	 *
	 * @return array<int>
	 */
	public function findIdsByThreadId(int $threadId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$ids = array_map(static fn (array $row): int => (int)$row['id'], $result->fetchAll());
		$result->closeCursor();
		return $ids;
	}

	/**
	 * Permanently delete all posts in a thread, including deleted posts
	 *
	 * @return int Number of posts removed
	 */
	public function deleteByThreadId(int $threadId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Permanently delete a single post by ID
	 *
	 * @return int Number of posts removed
	 */
	public function deleteById(int $postId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($postId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Find all posts for a thread, including deleted posts
	 *
	 * @return array<Post>
	 */
	public function findByThreadIdIncludingDeleted(int $threadId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}
}
