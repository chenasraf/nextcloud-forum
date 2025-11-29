<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2Date20251114222614 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createForumThreadSubsTable($schema);
		$this->fixForumUserStatsTable($schema);

		return $schema;
	}

	private function createForumThreadSubsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_thread_subs')) {
			return;
		}

		$table = $schema->createTable('forum_thread_subs');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('thread_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'thread_subs_uid_idx');
		$table->addIndex(['thread_id'], 'thread_subs_tid_idx');
		$table->addUniqueIndex(['user_id', 'thread_id'], 'thread_subs_uniq_idx');
	}

	private function fixForumUserStatsTable(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('forum_user_stats')) {
			return;
		}

		$table = $schema->getTable('forum_user_stats');

		// Check if already fixed (has id column)
		if ($table->hasColumn('id')) {
			return;
		}

		// Add id column as auto-increment
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		// Drop the old primary key on user_id
		$table->dropPrimaryKey();

		// Set id as the new primary key
		$table->setPrimaryKey(['id']);

		// Add unique index on user_id (since it's no longer the primary key)
		if (!$table->hasIndex('user_stats_user_id_uniq')) {
			$table->addUniqueIndex(['user_id'], 'user_stats_user_id_uniq');
		}

		// Add thread_count index
		if (!$table->hasIndex('user_stats_thread_count_idx')) {
			$table->addIndex(['thread_count'], 'user_stats_thread_count_idx');
		}
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Creating user statistics for all users...');

		$result = $this->rebuildAllUserStatsLegacy();

		$output->info(sprintf('Processed %d users', $result['users']));
		$output->info(sprintf('Created %d new forum users', $result['created']));
		$output->info(sprintf('Updated %d existing forum users', $result['updated']));
		$output->info('User statistics created successfully!');

		// Subscribe thread authors to their threads
		$this->subscribeAuthorsToThreads($output);
	}

	/**
	 * Rebuild user stats using the old table name (forum_user_stats)
	 * This is needed because Version8 hasn't renamed the table yet
	 */
	private function rebuildAllUserStatsLegacy(): array {
		// Get all user IDs from Nextcloud
		$users = [];
		$this->userManager->callForAllUsers(function ($user) use (&$users) {
			$users[] = $user->getUID();
		});

		$updated = 0;
		$created = 0;

		foreach ($users as $userId) {
			$wasCreated = $this->rebuildUserStatsLegacy($userId);
			if ($wasCreated) {
				$created++;
			} else {
				$updated++;
			}
		}

		return [
			'users' => count($users),
			'updated' => $updated,
			'created' => $created,
		];
	}

	/**
	 * Rebuild stats for a single user using the old table name
	 */
	private function rebuildUserStatsLegacy(string $userId): bool {
		// Count non-deleted threads created by this user
		$threadQb = $this->db->getQueryBuilder();
		$threadQb->select($threadQb->func()->count('*', 'count'))
			->from('forum_threads')
			->where($threadQb->expr()->eq('author_id', $threadQb->createNamedParameter($userId)))
			->andWhere($threadQb->expr()->isNull('deleted_at'));
		$threadResult = $threadQb->executeQuery();
		$threadCount = (int)($threadResult->fetchOne() ?? 0);
		$threadResult->closeCursor();

		// Count non-deleted posts created by this user (from non-deleted threads)
		// Exclude is_first_post posts as they are counted as threads
		$postQb = $this->db->getQueryBuilder();
		$postQb->select($postQb->func()->count('*', 'count'))
			->from('forum_posts', 'p')
			->innerJoin('p', 'forum_threads', 't', $postQb->expr()->eq('p.thread_id', 't.id'))
			->where($postQb->expr()->eq('p.author_id', $postQb->createNamedParameter($userId)))
			->andWhere($postQb->expr()->isNull('p.deleted_at'))
			->andWhere($postQb->expr()->isNull('t.deleted_at'))
			->andWhere($postQb->expr()->eq('p.is_first_post', $postQb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		$postResult = $postQb->executeQuery();
		$postCount = (int)($postResult->fetchOne() ?? 0);
		$postResult->closeCursor();

		// Get the timestamp of the last non-deleted post (from non-deleted threads)
		$lastPostQb = $this->db->getQueryBuilder();
		$lastPostQb->select('p.created_at')
			->from('forum_posts', 'p')
			->innerJoin('p', 'forum_threads', 't', $lastPostQb->expr()->eq('p.thread_id', 't.id'))
			->where($lastPostQb->expr()->eq('p.author_id', $lastPostQb->createNamedParameter($userId)))
			->andWhere($lastPostQb->expr()->isNull('p.deleted_at'))
			->andWhere($lastPostQb->expr()->isNull('t.deleted_at'))
			->orderBy('p.created_at', 'DESC')
			->setMaxResults(1);
		$lastPostResult = $lastPostQb->executeQuery();
		$lastPostAt = $lastPostResult->fetchOne();
		$lastPostResult->closeCursor();

		// Check if forum user record already exists (using OLD table name)
		$checkQb = $this->db->getQueryBuilder();
		$checkQb->select('user_id')
			->from('forum_user_stats')  // OLD table name!
			->where($checkQb->expr()->eq('user_id', $checkQb->createNamedParameter($userId)));
		$checkResult = $checkQb->executeQuery();
		$exists = $checkResult->fetch();
		$checkResult->closeCursor();

		$timestamp = time();

		if ($exists) {
			// Update existing record (using OLD table name)
			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('forum_user_stats')  // OLD table name!
				->set('thread_count', $updateQb->createNamedParameter($threadCount, IQueryBuilder::PARAM_INT))
				->set('post_count', $updateQb->createNamedParameter($postCount, IQueryBuilder::PARAM_INT))
				->set('updated_at', $updateQb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
				->where($updateQb->expr()->eq('user_id', $updateQb->createNamedParameter($userId)));

			if ($lastPostAt) {
				$updateQb->set('last_post_at', $updateQb->createNamedParameter((int)$lastPostAt, IQueryBuilder::PARAM_INT));
			}

			$updateQb->executeStatement();
			return false;
		} else {
			// Create new record (using OLD table name)
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('forum_user_stats')  // OLD table name!
				->values([
					'user_id' => $insertQb->createNamedParameter($userId),
					'thread_count' => $insertQb->createNamedParameter($threadCount, IQueryBuilder::PARAM_INT),
					'post_count' => $insertQb->createNamedParameter($postCount, IQueryBuilder::PARAM_INT),
					'last_post_at' => $insertQb->createNamedParameter($lastPostAt ? (int)$lastPostAt : null, IQueryBuilder::PARAM_INT),
					'created_at' => $insertQb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
					'updated_at' => $insertQb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
				]);

			try {
				$insertQb->executeStatement();
				return true;
			} catch (\Exception $e) {
				// If insert fails (race condition), try updating instead
				$updateQb = $this->db->getQueryBuilder();
				$updateQb->update('forum_user_stats')  // OLD table name!
					->set('thread_count', $updateQb->createNamedParameter($threadCount, IQueryBuilder::PARAM_INT))
					->set('post_count', $updateQb->createNamedParameter($postCount, IQueryBuilder::PARAM_INT))
					->set('updated_at', $updateQb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
					->where($updateQb->expr()->eq('user_id', $updateQb->createNamedParameter($userId)));

				if ($lastPostAt) {
					$updateQb->set('last_post_at', $updateQb->createNamedParameter((int)$lastPostAt, IQueryBuilder::PARAM_INT));
				}

				$updateQb->executeStatement();
				return false;
			}
		}
	}

	/**
	 * Subscribe all thread authors to their threads
	 */
	private function subscribeAuthorsToThreads(IOutput $output): void {
		$output->info('Subscribing thread authors to their threads...');

		$timestamp = time();
		$subscribed = 0;

		try {
			// Get all threads with their authors
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'author_id')
				->from('forum_threads');
			$result = $qb->executeQuery();
			$threads = $result->fetchAll();
			$result->closeCursor();

			foreach ($threads as $thread) {
				$threadId = (int)$thread['id'];
				$authorId = $thread['author_id'];

				// Check if author is already subscribed
				$qb = $this->db->getQueryBuilder();
				$qb->select('id')
					->from('forum_thread_subs')
					->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($authorId)));
				$result = $qb->executeQuery();
				$exists = $result->fetch();
				$result->closeCursor();

				if (!$exists) {
					// Subscribe the author to their thread
					$qb = $this->db->getQueryBuilder();
					$qb->insert('forum_thread_subs')
						->values([
							'thread_id' => $qb->createNamedParameter($threadId, IQueryBuilder::PARAM_INT),
							'user_id' => $qb->createNamedParameter($authorId),
							'created_at' => $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
						])
						->executeStatement();
					$subscribed++;
				}
			}

			$output->info(sprintf('Subscribed %d thread authors to their threads', $subscribed));
		} catch (\Exception $e) {
			$output->warning('Failed to subscribe thread authors: ' . $e->getMessage());
		}
	}

}
