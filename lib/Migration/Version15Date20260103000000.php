<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Version 15 Migration:
 * - Clean up duplicate roles that may exist from partial installations
 * - Add unique constraint on role_type to prevent future duplicates
 * - Re-run seeding to ensure all required data exists
 */
class Version15Date20260103000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Clean up duplicate roles before schema changes
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Forum: Checking for duplicate roles...');
		$this->cleanupDuplicateRoles($output);
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

		// Add unique index on role_type to prevent future duplicates
		if ($schema->hasTable('forum_roles')) {
			$table = $schema->getTable('forum_roles');

			// Check if the unique index already exists
			$hasUniqueIndex = false;
			foreach ($table->getIndexes() as $index) {
				if ($index->getColumns() === ['role_type'] && $index->isUnique()) {
					$hasUniqueIndex = true;
					break;
				}
			}

			if (!$hasUniqueIndex) {
				$output->info('Forum: Adding unique constraint on role_type...');
				$table->addUniqueIndex(['role_type'], 'forum_roles_role_type_uniq');
				return $schema;
			}
		}

		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Re-run seeding to ensure all required data exists
		// Pass throwOnError=false to avoid PostgreSQL transaction abort issues
		// If seeding fails, users can run "occ forum:repair-seeds" to retry
		try {
			SeedHelper::seedAll($output, false);
		} catch (\Exception $e) {
			// This should not happen with throwOnError=false, but handle it gracefully
			$this->logger->error('Forum migration: Seeding failed unexpectedly', ['exception' => $e->getMessage()]);
			$output->warning('Forum: Seeding failed. Run "occ forum:repair-seeds" after enabling the app to complete setup.');
		}
	}

	/**
	 * Remove duplicate roles, keeping only the first one of each type
	 */
	private function cleanupDuplicateRoles(IOutput $output): void {
		$roleTypes = ['admin', 'moderator', 'default', 'guest'];
		$duplicatesRemoved = 0;

		foreach ($roleTypes as $roleType) {
			// Find all roles of this type, ordered by ID
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter($roleType, IQueryBuilder::PARAM_STR)))
				->orderBy('id', 'ASC');

			$result = $qb->executeQuery();
			$roles = $result->fetchAll();
			$result->closeCursor();

			if (count($roles) <= 1) {
				continue;
			}

			// Keep the first one, delete the rest
			$keepId = (int)$roles[0]['id'];
			$deleteIds = array_map(fn ($r) => (int)$r['id'], array_slice($roles, 1));

			$this->logger->info('Forum migration: Found ' . count($deleteIds) . " duplicate '$roleType' roles, keeping ID $keepId");
			$output->info('  Found ' . count($deleteIds) . " duplicate '$roleType' roles, keeping ID $keepId");

			// Update user_roles to point to the kept role before deleting duplicates
			foreach ($deleteIds as $deleteId) {
				// Update forum_user_roles: reassign users from duplicate role to kept role
				$qb = $this->db->getQueryBuilder();
				$qb->update('forum_user_roles')
					->set('role_id', $qb->createNamedParameter($keepId, IQueryBuilder::PARAM_INT))
					->where($qb->expr()->eq('role_id', $qb->createNamedParameter($deleteId, IQueryBuilder::PARAM_INT)));

				try {
					$qb->executeStatement();
				} catch (\Exception $e) {
					// Might fail due to unique constraint if user already has the kept role - that's fine
					$this->logger->debug("Forum migration: Could not reassign user roles from $deleteId to $keepId: " . $e->getMessage());
				}

				// Delete orphaned user_roles entries (users who already had the kept role)
				$qb = $this->db->getQueryBuilder();
				$qb->delete('forum_user_roles')
					->where($qb->expr()->eq('role_id', $qb->createNamedParameter($deleteId, IQueryBuilder::PARAM_INT)));
				$qb->executeStatement();

				// Update forum_category_perms: reassign permissions from duplicate role to kept role
				$qb = $this->db->getQueryBuilder();
				$qb->update('forum_category_perms')
					->set('role_id', $qb->createNamedParameter($keepId, IQueryBuilder::PARAM_INT))
					->where($qb->expr()->eq('role_id', $qb->createNamedParameter($deleteId, IQueryBuilder::PARAM_INT)));

				try {
					$qb->executeStatement();
				} catch (\Exception $e) {
					// Might fail due to unique constraint - that's fine
					$this->logger->debug("Forum migration: Could not reassign category perms from $deleteId to $keepId: " . $e->getMessage());
				}

				// Delete orphaned category_perms entries
				$qb = $this->db->getQueryBuilder();
				$qb->delete('forum_category_perms')
					->where($qb->expr()->eq('role_id', $qb->createNamedParameter($deleteId, IQueryBuilder::PARAM_INT)));
				$qb->executeStatement();
			}

			// Now delete the duplicate roles
			$qb = $this->db->getQueryBuilder();
			$qb->delete('forum_roles')
				->where($qb->expr()->in('id', $qb->createNamedParameter($deleteIds, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();

			$duplicatesRemoved += count($deleteIds);
		}

		if ($duplicatesRemoved > 0) {
			$output->info("  Removed $duplicatesRemoved duplicate roles");
			$this->logger->info("Forum migration: Removed $duplicatesRemoved duplicate roles");
		} else {
			$output->info('  No duplicate roles found');
		}
	}
}
