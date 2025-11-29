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
 * Version 8 Migration:
 * - Remove slug column from forum_posts table (never used)
 * - Add signature column to forum_user_stats table
 */
class Version8Date20251128000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forum_posts')) {
			$table = $schema->getTable('forum_posts');

			// Drop the unique index on slug first (if exists)
			// Index was named 'forum_posts_slug_idx' in Version1
			if ($table->hasIndex('forum_posts_slug_idx')) {
				$table->dropIndex('forum_posts_slug_idx');
			}

			// Drop the slug column
			if ($table->hasColumn('slug')) {
				$table->dropColumn('slug');
			}
		}

		// Add signature column to user stats
		if ($schema->hasTable('forum_user_stats')) {
			$table = $schema->getTable('forum_user_stats');

			if (!$table->hasColumn('signature')) {
				$table->addColumn('signature', 'text', [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
