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
use Psr\Log\LoggerInterface;

/**
 * Version 30 Migration:
 * Repair migration to ensure Version 29 columns exist.
 * On some PostgreSQL instances, Version 29 may have failed silently,
 * leaving parent_id and hide_children_on_card columns missing.
 *
 * The header_id nullable change is handled separately in postSchemaChange
 * via raw SQL so that a failure there cannot block the new column additions.
 */
class Version30Date20260410000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$changed = false;

		if ($schema->hasTable('forum_categories')) {
			$table = $schema->getTable('forum_categories');

			if (!$table->hasColumn('parent_id')) {
				$table->addColumn('parent_id', 'integer', [
					'notnull' => false,
					'unsigned' => true,
					'default' => null,
				]);
				$table->addIndex(['parent_id'], 'forum_cat_parent_id_idx');
				$changed = true;
			}

			if (!$table->hasColumn('hide_children_on_card')) {
				$table->addColumn('hide_children_on_card', 'boolean', [
					'notnull' => false,
					'default' => false,
				]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Make header_id nullable via raw SQL so a failure here
		// does not roll back the column additions above.
		try {
			$platform = $this->db->getDatabasePlatform();
			$tableName = '*PREFIX*forum_categories';

			if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
				$this->db->executeStatement(
					'ALTER TABLE "' . $tableName . '" ALTER COLUMN "header_id" DROP NOT NULL'
				);
			} else {
				// MySQL / MariaDB / SQLite
				$this->db->executeStatement(
					'ALTER TABLE `' . $tableName . '` MODIFY `header_id` INT DEFAULT NULL'
				);
			}
		} catch (\Exception $e) {
			// Non-fatal: header_id may already be nullable, or the ALTER may not be
			// supported in this exact form. Category nesting (parentId) still works
			// for new categories as long as the column additions succeeded.
			$this->logger->warning('Version30: Could not make header_id nullable (may already be nullable): ' . $e->getMessage());
		}
	}
}
