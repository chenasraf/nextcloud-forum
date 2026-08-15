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
 * Version 31 Migration:
 * Add nullable attachment_upload_folder_id column to forum_categories so each
 * category can optionally pin a per-category upload destination by Nextcloud
 * file ID (so the destination resolves to each user's own path).
 */
class Version31Date20260522000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('forum_categories')) {
			return null;
		}

		$table = $schema->getTable('forum_categories');

		if ($table->hasColumn('attachment_upload_folder_id')) {
			return null;
		}

		$table->addColumn('attachment_upload_folder_id', 'integer', [
			'notnull' => false,
			'unsigned' => true,
			'default' => null,
		]);

		return $schema;
	}
}
