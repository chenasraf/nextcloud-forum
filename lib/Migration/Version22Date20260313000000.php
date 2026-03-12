<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Version 22 Migration:
 * - Mark existing installations as initialized if they already have seeded data
 */
class Version22Date20260313000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		try {
			// Check if forum_roles has rows
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from('forum_roles');
			$result = $qb->executeQuery();
			$rolesCount = (int)$result->fetchOne();
			$result->closeCursor();

			// Check if forum_user_roles has rows
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from('forum_user_roles');
			$result = $qb->executeQuery();
			$userRolesCount = (int)$result->fetchOne();
			$result->closeCursor();

			if ($rolesCount > 0 && $userRolesCount > 0) {
				$this->appConfig->setValueBool('forum', 'is_initialized', true, lazy: true);
				$this->logger->info('Forum migration: Marked existing installation as initialized');
				$output->info('Forum: Existing installation marked as initialized');
			} else {
				$output->info('Forum: Installation not yet seeded, skipping initialization flag');
			}
		} catch (\Exception $e) {
			$this->logger->warning('Forum migration: Failed to check initialization status', ['exception' => $e->getMessage()]);
			$output->warning('Forum: Could not check initialization status: ' . $e->getMessage());
		}
	}
}
