<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 9 Migration:
 * - Rename forum_user_stats to forum_users
 * - Run seed (creates initial data if not exists)
 */
class Version9Date20251129000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Rename forum_user_stats to forum_users using raw SQL
		// ISchemaWrapper doesn't have renameTable method
		$platform = $this->db->getDatabasePlatform();
		$prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');

		$oldTable = $prefix . 'forum_user_stats';
		$newTable = $prefix . 'forum_users';

		// Check if old table exists and new table doesn't
		$schema = $schemaClosure();
		if ($schema->hasTable('forum_user_stats') && !$schema->hasTable('forum_users')) {
			$output->info('Renaming forum_user_stats to forum_users...');

			// Use platform-specific rename syntax
			$platformName = $platform->getName();
			if ($platformName === 'mysql' || $platformName === 'mariadb') {
				$this->db->executeStatement("RENAME TABLE `{$oldTable}` TO `{$newTable}`");
			} elseif ($platformName === 'postgresql') {
				$this->db->executeStatement("ALTER TABLE \"{$oldTable}\" RENAME TO \"{$newTable}\"");
			} else {
				// SQLite and others
				$this->db->executeStatement("ALTER TABLE \"{$oldTable}\" RENAME TO \"{$newTable}\"");
			}

			$output->info('Table renamed successfully');
		}

		// Run seed after table rename (SeedHelper uses forum_users table)
		SeedHelper::seedAll($output);
	}
}
