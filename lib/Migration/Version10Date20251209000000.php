<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10Date20251209000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createForumBookmarksTable($schema);

		return $schema;
	}

	private function createForumBookmarksTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_bookmarks')) {
			return;
		}

		$table = $schema->createTable('forum_bookmarks');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('entity_type', 'string', [
			'notnull' => true,
			'length' => 32,
			'default' => 'thread',
		]);
		$table->addColumn('entity_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'bookmarks_uid_idx');
		$table->addIndex(['entity_type', 'entity_id'], 'bookmarks_entity_idx');
		$table->addUniqueIndex(['user_id', 'entity_type', 'entity_id'], 'bookmarks_uniq_idx');
		// Index for sorting by created_at DESC (most recent first)
		$table->addIndex(['user_id', 'entity_type', 'created_at'], 'bookmarks_uid_type_created_idx');
	}
}
