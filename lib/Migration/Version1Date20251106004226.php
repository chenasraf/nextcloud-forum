<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1Date20251106004226 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		//
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

		$this->createForumRolesTable($schema);
		$this->createUserStatsTable($schema);
		$this->createForumUserRolesTable($schema);
		$this->createForumCatHeadersTable($schema);
		$this->createForumCategoriesTable($schema);
		$this->createForumCategoryPermsTable($schema);
		$this->createForumBbcodesTable($schema);
		$this->createForumThreadsTable($schema);
		$this->createForumPostsTable($schema);
		$this->createForumReadMarkersTable($schema);
		$this->createForumReactionsTable($schema);

		return $schema;
	}

	private function createForumRolesTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_roles')) {
			return;
		}

		$table = $schema->createTable('forum_roles');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('name', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('description', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('can_access_admin_tools', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('can_edit_roles', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('can_edit_categories', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['name'], 'forum_roles_name_idx');
	}

	/**
	 * Create forum_users table (formerly forum_user_stats)
	 * Note: On fresh installs, this creates forum_users directly with the final schema.
	 * For progressive installs where forum_user_stats already exists,
	 * SeedHelper::ensureForumUsersTable() handles the rename.
	 *
	 * The table structure matches what Version2 transforms it to:
	 * - id: auto-increment primary key
	 * - user_id: unique string
	 * - signature: added in Version8
	 */
	private function createUserStatsTable(ISchemaWrapper $schema): void {
		// Skip if either table already exists (handles both fresh and progressive installs)
		if ($schema->hasTable('forum_users') || $schema->hasTable('forum_user_stats')) {
			return;
		}

		// Create forum_users directly with the final schema (matching Version2's transformation)
		$table = $schema->createTable('forum_users');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('post_count', 'integer', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('thread_count', 'integer', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('last_post_at', 'integer', [
			'notnull' => false,
			'unsigned' => true,
			'default' => null,
		]);
		$table->addColumn('deleted_at', 'integer', [
			'notnull' => false,
			'unsigned' => true,
			'default' => null,
		]);
		$table->addColumn('signature', 'text', [
			'notnull' => false,
			'default' => null,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'forum_users_user_id_uniq');
		$table->addIndex(['post_count'], 'forum_users_post_count_idx');
		$table->addIndex(['thread_count'], 'forum_users_thread_count_idx');
		$table->addIndex(['deleted_at'], 'forum_users_deleted_at_idx');
	}

	private function createForumUserRolesTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_user_roles')) {
			return;
		}

		$table = $schema->createTable('forum_user_roles');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('role_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'forum_user_roles_user_id_idx');
		$table->addIndex(['role_id'], 'forum_user_roles_role_id_idx');
		$table->addUniqueIndex(['user_id', 'role_id'], 'forum_user_roles_unique_idx');
	}

	private function createForumCatHeadersTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_cat_headers')) {
			return;
		}

		$table = $schema->createTable('forum_cat_headers');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('name', 'string', [
			'notnull' => true,
			'length' => 128,
		]);
		$table->addColumn('description', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('sort_order', 'integer', [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['sort_order'], 'forum_cat_headers_sort_idx');
	}

	private function createForumCategoriesTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_categories')) {
			return;
		}

		$table = $schema->createTable('forum_categories');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('header_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('name', 'string', [
			'notnull' => true,
			'length' => 128,
		]);
		$table->addColumn('description', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('slug', 'string', [
			'notnull' => true,
			'length' => 128,
		]);
		$table->addColumn('sort_order', 'integer', [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('thread_count', 'integer', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('post_count', 'integer', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['slug'], 'forum_categories_slug_idx');
		$table->addIndex(['header_id'], 'forum_categories_header_id_idx');
		$table->addIndex(['sort_order'], 'forum_categories_sort_idx');
	}

	private function createForumCategoryPermsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_category_perms')) {
			return;
		}

		$table = $schema->createTable('forum_category_perms');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('category_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('role_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('can_view', 'boolean', [
			'notnull' => false,
			'default' => true,
		]);
		$table->addColumn('can_post', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('can_reply', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('can_moderate', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['category_id'], 'forum_cat_perms_category_idx');
		$table->addIndex(['role_id'], 'forum_cat_perms_role_idx');
		$table->addUniqueIndex(['category_id', 'role_id'], 'forum_cat_perms_unique_idx');
	}

	private function createForumBbcodesTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_bbcodes')) {
			return;
		}

		$table = $schema->createTable('forum_bbcodes');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('tag', 'string', [
			'notnull' => true,
			'length' => 32,
		]);
		$table->addColumn('replacement', 'text', [
			'notnull' => true,
		]);
		$table->addColumn('example', 'text', [
			'notnull' => true,
		]);
		$table->addColumn('description', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('enabled', 'boolean', [
			'notnull' => false,
			'default' => true,
		]);
		$table->addColumn('parse_inner', 'boolean', [
			'notnull' => false,
			'default' => true,
		]);
		$table->addColumn('is_builtin', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('special_handler', 'string', [
			'notnull' => false,
			'length' => 64,
			'default' => null,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['tag'], 'forum_bbcodes_tag_idx');
	}

	private function createForumThreadsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_threads')) {
			return;
		}

		$table = $schema->createTable('forum_threads');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('category_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('author_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('title', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('slug', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('view_count', 'integer', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('post_count', 'integer', [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('last_post_id', 'bigint', [
			'notnull' => false,
			'unsigned' => true,
		]);
		$table->addColumn('is_locked', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('is_pinned', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('is_hidden', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('deleted_at', 'integer', [
			'notnull' => false,
			'unsigned' => true,
			'default' => null,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['slug'], 'forum_threads_slug_idx');
		$table->addIndex(['category_id'], 'forum_threads_category_id_idx');
		$table->addIndex(['author_id'], 'forum_threads_author_id_idx');
		$table->addIndex(['last_post_id'], 'forum_threads_last_post_id_idx');
		$table->addIndex(['is_pinned', 'updated_at'], 'forum_thread_pin_upd_idx');
	}

	private function createForumPostsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_posts')) {
			return;
		}

		$table = $schema->createTable('forum_posts');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('thread_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('author_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('content', 'text', [
			'notnull' => true,
		]);
		// Note: slug column was removed in Version8 - not needed for posts
		$table->addColumn('is_edited', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('is_first_post', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);
		$table->addColumn('edited_at', 'integer', [
			'notnull' => false,
			'unsigned' => true,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('deleted_at', 'integer', [
			'notnull' => false,
			'unsigned' => true,
			'default' => null,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['thread_id'], 'forum_posts_thread_id_idx');
		$table->addIndex(['author_id'], 'forum_posts_author_id_idx');
		$table->addIndex(['created_at'], 'forum_posts_created_at_idx');
		$table->addIndex(['is_first_post'], 'forum_posts_is_first_post_idx');
	}

	private function createForumReadMarkersTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_read_markers')) {
			return;
		}

		$table = $schema->createTable('forum_read_markers');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('thread_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('last_read_post_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('read_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'forum_read_mark_uid_idx');
		$table->addIndex(['thread_id'], 'forum_read_mark_tid_idx');
		$table->addUniqueIndex(['user_id', 'thread_id'], 'forum_read_mark_uniq_idx');
	}

	private function createForumReactionsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_reactions')) {
			return;
		}

		$table = $schema->createTable('forum_reactions');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('post_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', 'string', [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('reaction_type', 'string', [
			'notnull' => true,
			'length' => 32,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['post_id'], 'forum_reactions_post_id_idx');
		$table->addIndex(['user_id'], 'forum_reactions_user_id_idx');
		$table->addUniqueIndex(['post_id', 'user_id', 'reaction_type'], 'forum_reactions_unique_idx');
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Seed initial data after schema is created
		$this->seedInitialData($output);
	}

	/**
	 * Seed initial data after schema is created
	 * Each step is independent and can succeed/fail without affecting others
	 *
	 * @param IOutput $output
	 */
	private function seedInitialData(IOutput $output): void {
		//
	}
}
