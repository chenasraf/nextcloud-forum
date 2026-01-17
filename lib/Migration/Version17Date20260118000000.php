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
 * Version 17 Migration:
 * - Re-run seeding to ensure all required data exists
 *
 * Seeding is run after Version16 removes the incorrect unique constraint on role_type,
 * ensuring multiple custom roles can be created properly.
 */
class Version17Date20260118000000 extends SimpleMigrationStep {
	public function __construct(
		private LoggerInterface $logger,
	) {
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
}
