<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 18 Migration:
 * - Make forum_read_markers polymorphic by adding entity_id and marker_type columns
 * - Make last_read_post_id nullable (category markers don't need it)
 * - Copy thread_id values to entity_id
 * - Drop old indexes
 */
class Version18Date20260214000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return ISchemaWrapper|null
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('forum_read_markers')) {
			return null;
		}

		$table = $schema->getTable('forum_read_markers');

		// Add entity_id column (will be populated from thread_id in postSchemaChange)
		if (!$table->hasColumn('entity_id')) {
			$output->info('Forum: Adding entity_id column to forum_read_markers...');
			$table->addColumn('entity_id', 'bigint', [
				'notnull' => true,
				'unsigned' => true,
				'default' => 0,
			]);
		}

		// Add marker_type column
		if (!$table->hasColumn('marker_type')) {
			$output->info('Forum: Adding marker_type column to forum_read_markers...');
			$table->addColumn('marker_type', 'string', [
				'notnull' => true,
				'length' => 16,
				'default' => 'thread',
			]);
		}

		// Make last_read_post_id nullable (category markers don't use it)
		$lastReadPostIdCol = $table->getColumn('last_read_post_id');
		$lastReadPostIdCol->setNotnull(false);
		$lastReadPostIdCol->setDefault(null);

		// Drop old indexes
		if ($table->hasIndex('forum_read_mark_uniq_idx')) {
			$output->info('Forum: Dropping old unique index forum_read_mark_uniq_idx...');
			$table->dropIndex('forum_read_mark_uniq_idx');
		}
		if ($table->hasIndex('forum_read_mark_tid_idx')) {
			$output->info('Forum: Dropping old index forum_read_mark_tid_idx...');
			$table->dropIndex('forum_read_mark_tid_idx');
		}

		return $schema;
	}

	/**
	 * Copy thread_id values to entity_id
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Forum: Copying thread_id values to entity_id...');

		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_read_markers')
			->set('entity_id', 'thread_id');
		$qb->executeStatement();

		$output->info('Forum: thread_id values copied to entity_id successfully.');
	}
}
