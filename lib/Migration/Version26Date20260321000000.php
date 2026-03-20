<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Version 26 Migration:
 * - Add last_reply_author_id and last_reply_at columns to forum_threads
 * - Backfill from existing posts data
 */
class Version26Date20260321000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('forum_threads')) {
			return null;
		}

		$table = $schema->getTable('forum_threads');

		if (!$table->hasColumn('last_reply_author_id')) {
			$table->addColumn('last_reply_author_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		if (!$table->hasColumn('last_reply_at')) {
			$table->addColumn('last_reply_at', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Forum: Backfilling last_reply_author_id and last_reply_at from posts …');

		// Find threads that have a last_post_id pointing to a non-first-post
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id', 'p.author_id', 'p.created_at')
			->from('forum_threads', 't')
			->innerJoin('t', 'forum_posts', 'p', $qb->expr()->eq('t.last_post_id', 'p.id'))
			->where($qb->expr()->eq('p.is_first_post', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNull('p.deleted_at'))
			->andWhere($qb->expr()->isNull('t.deleted_at'));
		$result = $qb->executeQuery();

		$count = 0;
		while ($row = $result->fetch()) {
			$update = $this->db->getQueryBuilder();
			$update->update('forum_threads')
				->set('last_reply_author_id', $update->createNamedParameter($row['author_id']))
				->set('last_reply_at', $update->createNamedParameter((int)$row['created_at'], IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
			$count++;
		}
		$result->closeCursor();

		$output->info("Forum: Backfilled last reply info for $count threads.");
	}
}
