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
 * Version 16 Migration:
 * - Remove unique constraint on role_type column to allow multiple custom roles
 *
 * The unique constraint was incorrectly added in Version15, which prevented
 * creating more than one custom role (since all custom roles have role_type='custom').
 */
class Version16Date20260117000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return ISchemaWrapper|null
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forum_roles')) {
			$table = $schema->getTable('forum_roles');

			// Remove the unique index on role_type if it exists
			// This was incorrectly added in Version15 and prevents creating multiple custom roles
			if ($table->hasIndex('forum_roles_role_type_uniq')) {
				$output->info('Forum: Removing unique constraint on role_type to allow multiple custom roles...');
				$table->dropIndex('forum_roles_role_type_uniq');
				return $schema;
			}
		}

		return null;
	}
}
