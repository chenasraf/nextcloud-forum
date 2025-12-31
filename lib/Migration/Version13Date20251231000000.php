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
 * Version 13 Migration:
 * - Ensure forum_users table exists (fixes fresh installs where table was missing)
 * - Run seed to ensure all required data exists
 *
 * This migration consolidates seeding into a single point and fixes an issue where
 * the forum_users table was not created on fresh installations.
 */
class Version13Date20251231000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// SeedHelper ensures forum_users table exists and seeds all required data
		SeedHelper::seedAll($output);
	}
}
