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
		$this->createForumUsersTable($schema);
		$this->createForumUserRolesTable($schema);
		$this->createForumCatHeadersTable($schema);
		$this->createForumCategoriesTable($schema);
		$this->createForumCategoryPermsTable($schema);
		$this->createForumBbcodesTable($schema);
		$this->createForumThreadsTable($schema);
		$this->createForumPostsTable($schema);
		$this->createForumReadMarkersTable($schema);
		$this->createForumReactionsTable($schema);
		$this->createForumAttachmentsTable($schema);

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
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('can_edit_roles', 'boolean', [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('can_edit_categories', 'boolean', [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['name'], 'forum_roles_name_idx');
	}

	private function createForumUsersTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_users')) {
			return;
		}

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
		$table->addUniqueIndex(['user_id'], 'forum_users_user_id_idx');
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
			'notnull' => true,
			'default' => true,
		]);
		$table->addColumn('can_post', 'boolean', [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('can_reply', 'boolean', [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('can_moderate', 'boolean', [
			'notnull' => true,
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
		$table->addColumn('description', 'text', [
			'notnull' => false,
		]);
		$table->addColumn('enabled', 'boolean', [
			'notnull' => true,
			'default' => true,
		]);
		$table->addColumn('parse_inner', 'boolean', [
			'notnull' => true,
			'default' => true,
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
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('is_pinned', 'boolean', [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('is_hidden', 'boolean', [
			'notnull' => true,
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
		$table->addColumn('slug', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('is_edited', 'boolean', [
			'notnull' => true,
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
		$table->addUniqueIndex(['slug'], 'forum_posts_slug_idx');
		$table->addIndex(['thread_id'], 'forum_posts_thread_id_idx');
		$table->addIndex(['author_id'], 'forum_posts_author_id_idx');
		$table->addIndex(['created_at'], 'forum_posts_created_at_idx');
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

	private function createForumAttachmentsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('forum_attachments')) {
			return;
		}

		$table = $schema->createTable('forum_attachments');
		$table->addColumn('id', 'bigint', [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('post_id', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('fileid', 'bigint', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('filename', 'string', [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('created_at', 'integer', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['post_id'], 'forum_attachments_post_id_idx');
		$table->addIndex(['fileid'], 'forum_attachments_fileid_idx');
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$userManager = \OC::$server->get(\OCP\IUserManager::class);
		$timestamp = time();

		// Create default roles

		$qb = $db->getQueryBuilder();
		$qb->insert('forum_roles')
			->values([
				'name' => $qb->createNamedParameter('Admin'),
				'description' => $qb->createNamedParameter('Administrator role with full permissions'),
				'can_access_admin_tools' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'can_edit_roles' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'can_edit_categories' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$adminRoleId = $qb->getLastInsertId();

		$qb = $db->getQueryBuilder();
		$qb->insert('forum_roles')
			->values([
				'name' => $qb->createNamedParameter('Moderator'),
				'description' => $qb->createNamedParameter('Moderator role with elevated permissions'),
				'can_access_admin_tools' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'can_edit_roles' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'can_edit_categories' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$moderatorRoleId = $qb->getLastInsertId();

		$qb = $db->getQueryBuilder();
		$qb->insert('forum_roles')
			->values([
				'name' => $qb->createNamedParameter('User'),
				'description' => $qb->createNamedParameter('Default user role with basic permissions'),
				'can_access_admin_tools' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'can_edit_roles' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'can_edit_categories' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$userRoleId = $qb->getLastInsertId();

		// Create category header
		$qb = $db->getQueryBuilder();
		$qb->insert('forum_cat_headers')
			->values([
				'name' => $qb->createNamedParameter('General'),
				'description' => $qb->createNamedParameter('General discussion categories'),
				'sort_order' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$headerId = $qb->getLastInsertId();

		// Create "General Discussions" category
		$qb = $db->getQueryBuilder();
		$qb->insert('forum_categories')
			->values([
				'header_id' => $qb->createNamedParameter($headerId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'name' => $qb->createNamedParameter('General Discussions'),
				'description' => $qb->createNamedParameter('A place for general conversations and discussions'),
				'slug' => $qb->createNamedParameter('general-discussions'),
				'sort_order' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'thread_count' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'post_count' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$generalCategoryId = $qb->getLastInsertId();

		// Create "Support" category
		$qb = $db->getQueryBuilder();
		$qb->insert('forum_categories')
			->values([
				'header_id' => $qb->createNamedParameter($headerId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'name' => $qb->createNamedParameter('Support'),
				'description' => $qb->createNamedParameter('Ask questions about the forum, provide feedback or report issues.'),
				'slug' => $qb->createNamedParameter('support'),
				'sort_order' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'thread_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$supportCategoryId = $qb->getLastInsertId();

		// Create default category permissions
		$categoryIds = [$generalCategoryId, $supportCategoryId];

		foreach ($categoryIds as $categoryId) {
			// Admin role - full access to all categories
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_category_perms')
				->values([
					'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'role_id' => $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_reply' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_moderate' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				])
				->executeStatement();

			// Moderator role - can view, post, reply, and moderate
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_category_perms')
				->values([
					'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'role_id' => $qb->createNamedParameter($moderatorRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_reply' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_moderate' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				])
				->executeStatement();

			// User role - can view, post, and reply (no moderation)
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_category_perms')
				->values([
					'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'role_id' => $qb->createNamedParameter($userRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_reply' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_moderate' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				])
				->executeStatement();
		}

		// Create default BBCodes
		// Note: Most BBCode tags (b, i, u, s, code, email, url, img, quote, youtube, font, size, color, etc.)
		// are now provided by the chriskonnertz/bbcode library and don't need to be stored in the database.
		// We only store custom BBCodes that extend the library's functionality.
		$bbcodes = [
			['tag' => 'icode', 'replacement' => '<code>{content}</code>', 'description' => 'Inline code', 'parse_inner' => false],
		];

		foreach ($bbcodes as $bbcode) {
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_bbcodes')
				->values([
					'tag' => $qb->createNamedParameter($bbcode['tag']),
					'replacement' => $qb->createNamedParameter($bbcode['replacement']),
					'description' => $qb->createNamedParameter($bbcode['description']),
					'enabled' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'parse_inner' => $qb->createNamedParameter($bbcode['parse_inner'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();
		}

		// Create forum users for all Nextcloud users
		$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$adminGroup = $groupManager->get('admin');

		$userManager->callForAllUsers(function ($user) use ($db, $timestamp, $userRoleId, $adminRoleId, $adminGroup) {
			$userId = $user->getUID();
			$isAdmin = $adminGroup && $adminGroup->inGroup($user);

			// Create forum user
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_users')
				->values([
					'user_id' => $qb->createNamedParameter($userId),
					'post_count' => $qb->createNamedParameter($userId === 'admin' ? 1 : 0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			// Assign User role to all users
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_user_roles')
				->values([
					'user_id' => $qb->createNamedParameter($userId),
					'role_id' => $qb->createNamedParameter($userRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			// Assign Admin role to admin group members
			if ($isAdmin) {
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_user_roles')
					->values([
						'user_id' => $qb->createNamedParameter($userId),
						'role_id' => $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					])
					->executeStatement();
			}
		});

		// Create welcome thread
		$qb = $db->getQueryBuilder();
		$qb->insert('forum_threads')
			->values([
				'category_id' => $qb->createNamedParameter($generalCategoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'author_id' => $qb->createNamedParameter('admin'),
				'title' => $qb->createNamedParameter('Welcome to Nextcloud Forums'),
				'slug' => $qb->createNamedParameter('welcome-to-nextcloud-forums'),
				'view_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'post_count' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'last_post_id' => $qb->createNamedParameter(null, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'is_locked' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'is_pinned' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'is_hidden' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$threadId = $qb->getLastInsertId();

		// Create welcome post
		$welcomeContent = "Welcome to the Nextcloud Forums!\n\n"
			. 'This is a community-driven forum built right into your Nextcloud instance. '
			. "Here you can discuss topics, share ideas, and collaborate with other users.\n\n"
			. "[b]Features:[/b]\n"
			. "• Create and reply to threads\n"
			. "• Organize discussions by categories\n"
			. "• Use BBCode for rich text formatting\n"
			. "• Attach files from your Nextcloud storage\n"
			. "• React to posts\n"
			. "• Track read/unread threads\n\n"
			. "[b]BBCode Examples:[/b]\n"
			. "• [b]Bold text[/b] - Use [icode][b]text[/b][/icode]\n"
			. "• [i]Italic text[/i] - Use [icode][i]text[/i][/icode]\n"
			. "• [u]Underlined text[/u] - Use [icode][u]text[/u][/icode]\n\n"
			. 'Feel free to start a new discussion or reply to existing threads. Happy posting!';

		$qb = $db->getQueryBuilder();
		$qb->insert('forum_posts')
			->values([
				'thread_id' => $qb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'author_id' => $qb->createNamedParameter('admin'),
				'content' => $qb->createNamedParameter($welcomeContent),
				'slug' => $qb->createNamedParameter('welcome-to-nextcloud-forums-1'),
				'is_edited' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'edited_at' => $qb->createNamedParameter(null, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$postId = $qb->getLastInsertId();

		// Update thread's last_post_id
		$qb = $db->getQueryBuilder();
		$qb->update('forum_threads')
			->set('last_post_id', $qb->createNamedParameter($postId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
