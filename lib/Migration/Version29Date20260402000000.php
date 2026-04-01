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
 * Version 29 Migration:
 * - Add parent_id column to forum_categories for subcategory support
 * - Add hide_children_on_card column to forum_categories
 * - Make header_id nullable (child categories don't have their own header)
 */
class Version29Date20260402000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forum_categories')) {
			$table = $schema->getTable('forum_categories');

			// Make header_id nullable - child categories inherit header from parent chain
			if ($table->hasColumn('header_id')) {
				$column = $table->getColumn('header_id');
				$column->setNotnull(false);
				$column->setDefault(null);
			}

			if (!$table->hasColumn('parent_id')) {
				$table->addColumn('parent_id', 'integer', [
					'notnull' => false,
					'unsigned' => true,
					'default' => null,
				]);
				$table->addIndex(['parent_id'], 'forum_cat_parent_id_idx');
			}

			if (!$table->hasColumn('hide_children_on_card')) {
				$table->addColumn('hide_children_on_card', 'boolean', [
					'notnull' => false,
					'default' => false,
				]);
			}
		}

		return $schema;
	}
}
