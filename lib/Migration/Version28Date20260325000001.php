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
 * Version 28 Migration:
 * - Add can_access_moderation column to forum_roles
 * - Backfill: set true for admin and moderator roles
 */
class Version28Date20260325000001 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forum_roles')) {
			$table = $schema->getTable('forum_roles');

			if (!$table->hasColumn('can_access_moderation')) {
				$table->addColumn('can_access_moderation', 'boolean', [
					'notnull' => false,
					'default' => false,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('forum_roles')
			->set('can_access_moderation', $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->orX(
				$qb->expr()->eq('role_type', $qb->createNamedParameter('admin')),
				$qb->expr()->eq('role_type', $qb->createNamedParameter('moderator')),
			));
		$qb->executeStatement();

		$output->info('Backfilled can_access_moderation for admin and moderator roles');
	}
}
