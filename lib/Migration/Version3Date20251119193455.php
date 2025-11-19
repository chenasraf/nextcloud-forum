<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version3Date20251119193455 extends SimpleMigrationStep {
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

		// TODO add migration logic

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Fix admin role assignments for existing installations
		$this->fixAdminRoleAssignments();
	}

	/**
	 * Fix admin role assignments for users who ran the migration before the isAdmin() fix
	 */
	private function fixAdminRoleAssignments(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$userManager = \OC::$server->get(\OCP\IUserManager::class);
		$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$timestamp = time();

		// Get the Admin role ID
		$qb = $db->getQueryBuilder();
		$qb->select('id')
			->from('forum_roles')
			->where($qb->expr()->eq('name', $qb->createNamedParameter('Admin')));
		$result = $qb->executeQuery();
		$adminRole = $result->fetch();
		$result->closeCursor();

		if (!$adminRole) {
			// Admin role doesn't exist, nothing to fix
			return;
		}

		$adminRoleId = $adminRole['id'];

		// Check if there are any users already assigned to the Admin role
		$qb = $db->getQueryBuilder();
		$qb->select('id')
			->from('forum_user_roles')
			->where($qb->expr()->eq('role_id', $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$hasAdmins = $result->fetch();
		$result->closeCursor();

		if ($hasAdmins) {
			// Admin roles are already assigned, nothing to fix
			return;
		}

		// No admins found - assign Admin role to all Nextcloud admins
		$userManager->callForAllUsers(function ($user) use ($db, $timestamp, $adminRoleId, $groupManager) {
			$userId = $user->getUID();
			$isAdmin = $groupManager->isAdmin($userId);

			if ($isAdmin) {
				// Check if this user already has the admin role (shouldn't happen, but be safe)
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_user_roles')
					->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
					->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
				$result = $qb->executeQuery();
				$exists = $result->fetch();
				$result->closeCursor();

				if (!$exists) {
					// Assign Admin role
					$qb = $db->getQueryBuilder();
					$qb->insert('forum_user_roles')
						->values([
							'user_id' => $qb->createNamedParameter($userId),
							'role_id' => $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						])
						->executeStatement();
				}
			}
		});
	}
}
