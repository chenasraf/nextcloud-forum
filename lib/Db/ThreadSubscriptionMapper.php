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
 * @template-extends QBMapper<ThreadSubscription>
 */
class ThreadSubscriptionMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_thread_subs'), ThreadSubscription::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): ThreadSubscription {
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
	public function findByUserAndThread(string $userId, int $threadId): ThreadSubscription {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Check if a user is subscribed to a thread
	 */
	public function isUserSubscribed(string $userId, int $threadId): bool {
		try {
			$this->findByUserAndThread($userId, $threadId);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * Get all subscribed users for a thread
	 *
	 * @return array<ThreadSubscription>
	 */
	public function findByThread(int $threadId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Get all thread subscriptions for a user
	 *
	 * @return array<ThreadSubscription>
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
	 * Subscribe a user to a thread
	 */
	public function subscribe(string $userId, int $threadId): ThreadSubscription {
		// Check if already subscribed
		if ($this->isUserSubscribed($userId, $threadId)) {
			return $this->findByUserAndThread($userId, $threadId);
		}

		// Create new subscription
		$subscription = new ThreadSubscription();
		$subscription->setUserId($userId);
		$subscription->setThreadId($threadId);
		$subscription->setCreatedAt(time());
		return $this->insert($subscription);
	}

	/**
	 * Unsubscribe a user from a thread
	 */
	public function unsubscribe(string $userId, int $threadId): void {
		try {
			$subscription = $this->findByUserAndThread($userId, $threadId);
			$this->delete($subscription);
		} catch (DoesNotExistException $e) {
			// Already not subscribed, nothing to do
		}
	}

	/**
	 * @return array<ThreadSubscription>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
