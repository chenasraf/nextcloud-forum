<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version12Date20251218000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createPostHistoryTable($schema);

		return $schema;
	}

	private function createPostHistoryTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_post_history')) {
			return;
		}

		$table = $schema->createTable('forum_post_history');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		// Reference to the post being edited
		$table->addColumn('post_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		// The content before the edit (the historical version)
		$table->addColumn('content', 'text', [
			'notnull' => true,
		]);
		// User who made the edit (could be different from original author if admin/mod edited)
		$table->addColumn('edited_by', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		// When this version was replaced (when the edit happened)
		$table->addColumn('edited_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['post_id'], 'post_history_post_idx');
		$table->addIndex(['post_id', 'edited_at'], 'post_history_post_time_idx');
	}
}
