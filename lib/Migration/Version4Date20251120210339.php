<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version4Date20251120210339 extends SimpleMigrationStep {
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

		// Add color columns to forum_roles table
		if ($schema->hasTable('forum_roles')) {
			$table = $schema->getTable('forum_roles');

			if (!$table->hasColumn('color_light')) {
				$table->addColumn('color_light', 'string', [
					'notnull' => false,
					'length' => 7,
					'default' => null,
				]);
			}

			if (!$table->hasColumn('color_dark')) {
				$table->addColumn('color_dark', 'string', [
					'notnull' => false,
					'length' => 7,
					'default' => null,
				]);
			}
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->updateDefaultRoleColors();
	}

	/**
	 * Update default roles with colors that are legible in both light and dark modes
	 */
	private function updateDefaultRoleColors(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);

		// Define colors for default roles
		// Light mode uses darker colors, dark mode uses lighter colors for better contrast
		$roleColors = [
			1 => [
				'light' => '#dc2626', // Red 600
				'dark' => '#f87171',  // Red 400
			],
			2 => [
				'light' => '#2563eb', // Blue 600
				'dark' => '#60a5fa',  // Blue 400
			],
			'User' => [
				'light' => '#059669', // Emerald 600
				'dark' => '#34d399',  // Emerald 400
			],
		];

		foreach ($roleColors as $roleId => $colors) {
			$qb = $db->getQueryBuilder();
			$qb->update('forum_roles')
				->set('color_light', $qb->createNamedParameter($colors['light']))
				->set('color_dark', $qb->createNamedParameter($colors['dark']))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($roleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
	}
}
