<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version23Date20260316000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forum_categories')) {
			$table = $schema->getTable('forum_categories');

			if (!$table->hasColumn('color')) {
				$table->addColumn('color', 'string', [
					'notnull' => false,
					'length' => 7,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('text_color')) {
				$table->addColumn('text_color', 'string', [
					'notnull' => false,
					'length' => 5,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
