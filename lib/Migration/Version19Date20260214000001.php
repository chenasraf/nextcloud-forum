<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 19 Migration:
 * - Drop thread_id column (data already copied to entity_id in Version18)
 * - Add new indexes for polymorphic read markers
 */
class Version19Date20260214000001 extends SimpleMigrationStep {
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

		// Drop thread_id column
		if ($table->hasColumn('thread_id')) {
			$output->info('Forum: Dropping thread_id column from forum_read_markers...');
			$table->dropColumn('thread_id');
		}

		// Add unique index on (user_id, marker_type, entity_id)
		if (!$table->hasIndex('forum_read_mark_uniq_idx')) {
			$output->info('Forum: Adding unique index forum_read_mark_uniq_idx...');
			$table->addUniqueIndex(['user_id', 'marker_type', 'entity_id'], 'forum_read_mark_uniq_idx');
		}

		// Add index on entity_id
		if (!$table->hasIndex('forum_read_mark_eid_idx')) {
			$output->info('Forum: Adding index forum_read_mark_eid_idx...');
			$table->addIndex(['entity_id'], 'forum_read_mark_eid_idx');
		}

		return $schema;
	}
}
