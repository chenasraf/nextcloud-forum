<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCA\Forum\Db\Role;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7Date20251124120000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private \OCP\IConfig $systemConfig,
		private \OCP\AppFramework\Services\IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Add role columns to forum_roles table
		if ($schema->hasTable('forum_roles')) {
			$table = $schema->getTable('forum_roles');

			if (!$table->hasColumn('is_system_role')) {
				$table->addColumn('is_system_role', 'boolean', [
					'notnull' => false,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('role_type')) {
				$table->addColumn('role_type', 'string', [
					'notnull' => false,
					'default' => 'custom',
					'length' => 20,
				]);
			}

			// Add index on role_type column to optimize findByRoleType() lookups
			// This is frequently called for guest user access checks
			if (!$table->hasIndex('forum_roles_role_type_idx')) {
				$table->addIndex(['role_type'], 'forum_roles_role_type_idx');
			}
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateConfigValues($output);
		$this->updateExistingRoleFlags($output);
		// Note: SeedHelper::seedAll() is called in Version9 after table rename
	}

	/**
	 * Migrate config values from system config to app config
	 * Previous versions used IConfig (system config), now using IAppConfig (app config)
	 */
	private function migrateConfigValues(IOutput $output): void {
		$configKeys = ['title', 'subtitle'];
		$migrated = 0;

		foreach ($configKeys as $key) {
			// Check if value exists in system config
			$systemValue = $this->systemConfig->getSystemValue($key, null);

			// Only migrate if:
			// 1. System config has a value
			// 2. App config doesn't have a value yet (to avoid overwriting)
			if ($systemValue !== null) {
				try {
					$appValue = $this->appConfig->getAppValueString($key, '', true);

					// If app config is empty or default, migrate from system config
					if ($appValue === '') {
						$this->appConfig->setAppValueString($key, $systemValue, true);
						// Delete the old system config value after successful migration
						$this->systemConfig->deleteSystemValue($key);
						$migrated++;
						$output->info("Migrated forum '$key' from system config to app config and removed old value");
					}
				} catch (\Exception $e) {
					$output->warning("Failed to migrate config '$key': " . $e->getMessage());
				}
			}
		}

		if ($migrated === 0) {
			$output->info('No forum config values needed migration');
		}
	}

	/**
	 * Update existing system roles with appropriate flags
	 * This runs BEFORE seeding to ensure existing roles have role_type set
	 */
	private function updateExistingRoleFlags(IOutput $output): void {
		// First, set defaults for all existing roles that don't have role_type set
		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_roles')
			->set('is_system_role', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->set('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_CUSTOM, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->isNull('role_type'));
		$updated = $qb->executeStatement();
		if ($updated > 0) {
			$output->info("Set defaults for $updated existing roles");
		}

		// Now update the three original system roles (Admin, Moderator, User) with their specific values
		// Only updates roles that exist and have role_type='custom' (just set above)

		// Set role_type='admin' for role 1 (Admin) if it exists
		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_roles')
			->set('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_ADMIN, IQueryBuilder::PARAM_STR))
			->set('is_system_role', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_CUSTOM, IQueryBuilder::PARAM_STR))
			);
		$updated = $qb->executeStatement();
		if ($updated > 0) {
			$output->info("Set role_type='" . Role::ROLE_TYPE_ADMIN . "' for role 1 (Admin)");
		}

		// Set role_type='moderator' for role 2 (Moderator) if it exists
		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_roles')
			->set('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_MODERATOR, IQueryBuilder::PARAM_STR))
			->set('is_system_role', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_CUSTOM, IQueryBuilder::PARAM_STR))
			);
		$updated = $qb->executeStatement();
		if ($updated > 0) {
			$output->info("Set role_type='" . Role::ROLE_TYPE_MODERATOR . "' for role 2 (Moderator)");
		}

		// Set role_type='default' for role 3 (User) if it exists
		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_roles')
			->set('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_DEFAULT, IQueryBuilder::PARAM_STR))
			->set('is_system_role', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter(3, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_CUSTOM, IQueryBuilder::PARAM_STR))
			);
		$updated = $qb->executeStatement();
		if ($updated > 0) {
			$output->info("Set role_type='" . Role::ROLE_TYPE_DEFAULT . "' for role 3 (User)");
		}
	}
}
