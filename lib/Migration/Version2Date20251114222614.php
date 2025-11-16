<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2Date20251114222614 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
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

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
