<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCA\Forum\AppInfo\Application;
use OCA\Forum\Service\UserRoleService;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version6Date20251122233018 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
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

		// TODO add migration logic

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Remove Admin role permissions from categories
		// Admin role now has hardcoded full access to all categories
		$qb = $this->db->getQueryBuilder();
		$qb->delete(Application::tableName('forum_category_perms'))
			->where(
				$qb->expr()->eq('role_id', $qb->createNamedParameter(UserRoleService::ROLE_ADMIN, IQueryBuilder::PARAM_INT))
			);
		$deletedCount = $qb->executeStatement();

		$output->info("Removed $deletedCount Admin role permission entries from categories (Admin has hardcoded full access)");
	}
}
