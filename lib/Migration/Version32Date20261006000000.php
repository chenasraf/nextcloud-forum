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
 * Version 32 Migration:
 * Add parent_post_id and root_reply_id to forum_posts for nested replies.
 * parent_post_id is the post being replied to; root_reply_id is the top-level
 * reply the post descends from, so a page of top-level replies can load all of
 * their descendants in a single query.
 */
class Version32Date20261006000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('forum_posts')) {
			return null;
		}

		$table = $schema->getTable('forum_posts');
		$changed = false;

		if (!$table->hasColumn('parent_post_id')) {
			$table->addColumn('parent_post_id', 'bigint', [
				'notnull' => false,
				'unsigned' => true,
				'default' => null,
			]);
			$table->addIndex(['parent_post_id'], 'forum_posts_parent_idx');
			$changed = true;
		}

		if (!$table->hasColumn('root_reply_id')) {
			$table->addColumn('root_reply_id', 'bigint', [
				'notnull' => false,
				'unsigned' => true,
				'default' => null,
			]);
			$table->addIndex(['root_reply_id'], 'forum_posts_root_reply_idx');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
