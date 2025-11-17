<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCA\Forum\Service\UserStatsService;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2Date20251114222614 extends SimpleMigrationStep {
	public function __construct(
		private UserStatsService $userStatsService,
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
		$output->info('Rebuilding user statistics...');

		$result = $this->userStatsService->rebuildAllUserStats();

		$output->info(sprintf('Found %d users to process', $result['users']));
		$output->info(sprintf('Created %d new user stats', $result['created']));
		$output->info(sprintf('Updated %d existing user stats', $result['updated']));
		$output->info('User statistics rebuilt successfully!');
	}

}
