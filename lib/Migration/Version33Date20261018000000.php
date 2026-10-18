<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 33 Migration:
 * Delete all category read markers.
 * Category markers act as "mark as read" watermarks: every post in the
 * category created up to the marker counts as read. The existing rows record
 * category visits instead, and kept as watermarks they would mark every thread
 * the user never opened as read.
 */
class Version33Date20261018000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('forum_read_markers')
			->where($qb->expr()->eq('marker_type', $qb->createNamedParameter('category', IQueryBuilder::PARAM_STR)));
		$deleted = $qb->executeStatement();

		$output->info("Deleted $deleted category read markers");
	}
}
