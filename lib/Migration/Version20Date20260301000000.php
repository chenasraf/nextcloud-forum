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
 * Version 20 Migration:
 * - Add target_type and target_id columns to forum_category_perms
 * - Copy role_id values to target_id as strings
 * - Drop old indexes
 */
class Version20Date20260301000000 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('forum_category_perms')) {
			return null;
		}

		$table = $schema->getTable('forum_category_perms');

		// Add target_type column
		if (!$table->hasColumn('target_type')) {
			$output->info('Forum: Adding target_type column to forum_category_perms...');
			$table->addColumn('target_type', 'string', [
				'notnull' => true,
				'length' => 16,
				'default' => 'role',
			]);
		}

		// Add target_id column (nullable initially for migration)
		if (!$table->hasColumn('target_id')) {
			$output->info('Forum: Adding target_id column to forum_category_perms...');
			$table->addColumn('target_id', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
		}

		// Drop old unique index
		if ($table->hasIndex('forum_cat_perms_unique_idx')) {
			$output->info('Forum: Dropping old unique index forum_cat_perms_unique_idx...');
			$table->dropIndex('forum_cat_perms_unique_idx');
		}

		// Drop old role_id index
		if ($table->hasIndex('forum_cat_perms_role_idx')) {
			$output->info('Forum: Dropping old index forum_cat_perms_role_idx...');
			$table->dropIndex('forum_cat_perms_role_idx');
		}

		return $schema;
	}

	/**
	 * Copy role_id values to target_id as strings
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Forum: Copying role_id values to target_id...');

		// Select all rows and update each one, converting int role_id to string target_id in PHP
		// This is cross-platform safe (works on MySQL, PostgreSQL, SQLite)
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'role_id')
			->from('forum_category_perms');
		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			$update = $this->db->getQueryBuilder();
			$update->update('forum_category_perms')
				->set('target_type', $update->createNamedParameter('role'))
				->set('target_id', $update->createNamedParameter((string)$row['role_id']))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
		}
		$result->closeCursor();

		$output->info('Forum: role_id values copied to target_id successfully.');
	}
}
