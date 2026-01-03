<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 14 Migration:
 * - Originally ran seed to ensure all required data exists
 * - Seeding moved to Version15 which first cleans up duplicate roles
 *
 * This migration is now a no-op but kept for migration history.
 */
class Version14Date20260101000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// No-op: Seeding moved to Version15 which first cleans up duplicate roles
	}
}
