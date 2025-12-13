<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11Date20251213000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createForumDraftsTable($schema);

		return $schema;
	}

	private function createForumDraftsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_drafts')) {
			return;
		}

		$table = $schema->createTable('forum_drafts');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		// Polymorphic entity type: 'thread' or 'post' (for future use)
		$table->addColumn('entity_type', 'string', [
			'notnull' => true,
			'length' => 32,
			'default' => 'thread',
		]);
		// Parent ID: category_id for thread drafts, thread_id for post drafts
		$table->addColumn('parent_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		// Title for thread drafts (nullable for post drafts)
		$table->addColumn('title', 'string', [
			'notnull' => false,
			'length' => 255,
		]);
		// Content is required
		$table->addColumn('content', 'text', [
			'notnull' => true,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'drafts_uid_idx');
		$table->addIndex(['user_id', 'entity_type'], 'drafts_uid_type_idx');
		// Index for finding drafts by user and parent (category for threads, thread for posts)
		$table->addIndex(['user_id', 'entity_type', 'parent_id'], 'drafts_uid_type_parent_idx');
		// Unique constraint: one draft per user per entity type per parent
		$table->addUniqueIndex(['user_id', 'entity_type', 'parent_id'], 'drafts_uniq_idx');
	}
}
