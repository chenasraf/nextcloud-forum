<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Version 21 Migration (runs after data migration in Version 20):
 * - Make target_id not null
 * - Drop role_id column
 * - Create new indexes for (category_id, target_type, target_id)
 */
class Version21Date20260301000001 extends SimpleMigrationStep {
	public function __construct(
		private LoggerInterface $logger,
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

		// Make target_id not null
		if ($table->hasColumn('target_id')) {
			$output->info('Forum: Making target_id column not null...');
			$column = $table->getColumn('target_id');
			$column->setNotnull(true);
			$column->setDefault('');
		}

		// Drop role_id column
		if ($table->hasColumn('role_id')) {
			$output->info('Forum: Dropping role_id column from forum_category_perms...');
			$table->dropColumn('role_id');
		}

		// Add unique index on (category_id, target_type, target_id)
		if (!$table->hasIndex('forum_cat_perms_uniq_idx')) {
			$output->info('Forum: Adding unique index forum_cat_perms_uniq_idx...');
			$table->addUniqueIndex(['category_id', 'target_type', 'target_id'], 'forum_cat_perms_uniq_idx');
		}

		// Add index on (target_type, target_id)
		if (!$table->hasIndex('forum_cat_perms_target_idx')) {
			$output->info('Forum: Adding index forum_cat_perms_target_idx...');
			$table->addIndex(['target_type', 'target_id'], 'forum_cat_perms_target_idx');
		}

		return $schema;
	}

	/**
	 * Run seeding after schema is finalized (target_type/target_id columns now exist)
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		try {
			SeedHelper::seedAll($output, false);
		} catch (\Exception $e) {
			$this->logger->error('Forum migration: Seeding failed unexpectedly', ['exception' => $e->getMessage()]);
			$output->warning('Forum: Seeding failed. Run "occ forum:repair-seeds" after enabling the app to complete setup.');
		}
	}
}
