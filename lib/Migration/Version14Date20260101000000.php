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
 * - Run seed to ensure all required data exists
 *
 * This migration fixes an issue from Version 13 where seeding used hardcoded role IDs
 * that may not match the actual role IDs in the database during upgrades.
 * The fix ensures roles are looked up by role_type instead of by ID.
 */
class Version14Date20260101000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// SeedHelper now uses role_type instead of hardcoded IDs to find roles
		SeedHelper::seedAll($output);
	}
}
