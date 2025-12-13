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
 * Version 9 Migration:
 * - Rename forum_user_stats to forum_users (handled by SeedHelper)
 * - Run seed (creates initial data if not exists)
 */
class Version9Date20251129000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// SeedHelper handles table rename (forum_user_stats -> forum_users) and seeding
		SeedHelper::seedAll($output);
	}
}
