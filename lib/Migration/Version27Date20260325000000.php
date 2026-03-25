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
 * Version 10 Migration:
 * - Add can_manage_users and can_edit_bbcodes columns to forum_roles
 * - Backfill: can_manage_users = can_access_admin_tools OR can_edit_roles
 * - Backfill: can_edit_bbcodes = can_access_admin_tools
 */
class Version27Date20260325000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forum_roles')) {
			$table = $schema->getTable('forum_roles');

			if (!$table->hasColumn('can_manage_users')) {
				$table->addColumn('can_manage_users', 'boolean', [
					'notnull' => false,
					'default' => false,
				]);
			}

			if (!$table->hasColumn('can_edit_bbcodes')) {
				$table->addColumn('can_edit_bbcodes', 'boolean', [
					'notnull' => false,
					'default' => false,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Backfill can_manage_users: anyone who had canAccessAdminTools OR canEditRoles
		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_roles')
			->set('can_manage_users', $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->orX(
				$qb->expr()->eq('can_access_admin_tools', $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)),
				$qb->expr()->eq('can_edit_roles', $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)),
			));
		$qb->executeStatement();

		// Backfill can_edit_bbcodes: anyone who had canAccessAdminTools
		$qb2 = $this->db->getQueryBuilder();
		$qb2->update('forum_roles')
			->set('can_edit_bbcodes', $qb2->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL))
			->where($qb2->expr()->eq('can_access_admin_tools', $qb2->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)));
		$qb2->executeStatement();

		$output->info('Backfilled can_manage_users and can_edit_bbcodes from existing permissions');
	}
}
