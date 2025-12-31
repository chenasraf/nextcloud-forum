<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use OCP\Migration\SimpleMigrationStep;

/**
 * Version 9 Migration:
 * - Originally handled table rename and seeding
 * - Seeding moved to Version13 to fix fresh install issues
 * - This migration is now a no-op for backwards compatibility
 */
class Version9Date20251129000000 extends SimpleMigrationStep {
}
