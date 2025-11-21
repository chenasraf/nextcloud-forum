<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Migration;

/**
 * Helper class for seeding initial forum data
 * Can be used by multiple migrations to ensure data exists
 */
class SeedHelper {
	/**
	 * Seed all initial data
	 * Each function checks its own state and returns early if already seeded
	 */
	public static function seedAll(): void {
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$logger->info('Forum seeding: Starting data seed/repair');

		// Each function checks its own state and returns early if already seeded
		// They run independently so one failure doesn't block others
		self::seedDefaultRoles();
		self::seedCategoryHeaders();
		self::seedDefaultCategories();
		self::seedCategoryPermissions();
		self::seedDefaultBBCodes();
		self::assignUserRoles();
		self::seedWelcomeThread();

		$logger->info('Forum seeding: Completed data seed/repair');
	}

	/**
	 * Seed default roles (Admin, Moderator, User)
	 */
	public static function seedDefaultRoles(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Check if roles already exist
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('id', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: Default roles already exist, skipping');
				return;
			}

			$db->beginTransaction();

			// Create default roles
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_roles')
				->values([
					'name' => $qb->createNamedParameter($l->t('Admin')),
					'description' => $qb->createNamedParameter($l->t('Administrator role with full permissions')),
					'can_access_admin_tools' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_edit_roles' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_edit_categories' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			$qb = $db->getQueryBuilder();
			$qb->insert('forum_roles')
				->values([
					'name' => $qb->createNamedParameter($l->t('Moderator')),
					'description' => $qb->createNamedParameter($l->t('Moderator role with elevated permissions')),
					'can_access_admin_tools' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_edit_roles' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_edit_categories' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			$qb = $db->getQueryBuilder();
			$qb->insert('forum_roles')
				->values([
					'name' => $qb->createNamedParameter($l->t('User')),
					'description' => $qb->createNamedParameter($l->t('Default user role with basic permissions')),
					'can_access_admin_tools' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_edit_roles' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'can_edit_categories' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			$db->commit();
			$logger->info('Forum seeding: Created default roles (IDs 1, 2, 3)');
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create default roles', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Seed category headers
	 */
	public static function seedCategoryHeaders(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Check if headers already exist
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_cat_headers')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: Category headers already exist, skipping');
				return;
			}

			$db->beginTransaction();

			// Create "General" category header
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_cat_headers')
				->values([
					'name' => $qb->createNamedParameter($l->t('General')),
					'description' => $qb->createNamedParameter($l->t('General discussion categories')),
					'sort_order' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			$db->commit();
			$logger->info('Forum seeding: Created category headers');
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create category headers', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Seed default categories
	 */
	public static function seedDefaultCategories(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Check if categories already exist
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_categories')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: Categories already exist, skipping');
				return;
			}

			// Get the header ID (should be 1 if created by seedCategoryHeaders)
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_cat_headers')
				->orderBy('id', 'ASC')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$header = $result->fetch();
			$result->closeCursor();

			if (!$header) {
				$logger->warning('Forum seeding: No category headers found, skipping category creation');
				return;
			}

			$headerId = (int)$header['id'];
			$db->beginTransaction();

			// Create "General Discussions" category
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_categories')
				->values([
					'header_id' => $qb->createNamedParameter($headerId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'name' => $qb->createNamedParameter($l->t('General Discussions')),
					'description' => $qb->createNamedParameter($l->t('A place for general conversations and discussions')),
					'slug' => $qb->createNamedParameter('general-discussions'),
					'sort_order' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'thread_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			// Create "Support" category
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_categories')
				->values([
					'header_id' => $qb->createNamedParameter($headerId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'name' => $qb->createNamedParameter($l->t('Support')),
					'description' => $qb->createNamedParameter($l->t('Ask questions about the forum, provide feedback or report issues.')),
					'slug' => $qb->createNamedParameter('support'),
					'sort_order' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'thread_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			$db->commit();
			$logger->info('Forum seeding: Created default categories');
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create default categories', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Seed category permissions for all roles
	 */
	public static function seedCategoryPermissions(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

		try {
			// Check if permissions already exist
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_category_perms')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: Category permissions already exist, skipping');
				return;
			}

			// Get all category IDs
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_categories');
			$result = $qb->executeQuery();
			$categories = $result->fetchAll();
			$result->closeCursor();

			if (empty($categories)) {
				$logger->warning('Forum seeding: No categories found, skipping permission creation');
				return;
			}

			$db->beginTransaction();

			// Role IDs: 1=Admin, 2=Moderator, 3=User
			foreach ($categories as $category) {
				$categoryId = (int)$category['id'];

				// Admin role - full access
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_category_perms')
					->values([
						'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'role_id' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_reply' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_moderate' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					])
					->executeStatement();

				// Moderator role - can moderate
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_category_perms')
					->values([
						'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'role_id' => $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_reply' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_moderate' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					])
					->executeStatement();

				// User role - basic access
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_category_perms')
					->values([
						'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'role_id' => $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_reply' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'can_moderate' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					])
					->executeStatement();
			}

			$db->commit();
			$logger->info('Forum seeding: Created category permissions');
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create category permissions', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Seed default BBCodes
	 */
	public static function seedDefaultBBCodes(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Check if BBCodes already exist
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_bbcodes')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: BBCodes already exist, skipping');
				return;
			}

			$db->beginTransaction();

			$bbcodes = [
				[
					'tag' => 'icode',
					'replacement' => '<code>{content}</code>',
					'example' => '[icode]inline code[/icode]',
					'description' => $l->t('Inline code'),
					'parse_inner' => false,
					'is_builtin' => true,
					'special_handler' => null,
				],
				[
					'tag' => 'spoiler',
					'replacement' => '<details><summary>{title}</summary>{content}</details>',
					'example' => '[spoiler="Spoiler Title"]Hidden content[/spoiler]',
					'description' => $l->t('Spoilers'),
					'parse_inner' => false,
					'is_builtin' => true,
					'special_handler' => null,
				],
				[
					'tag' => 'attachment',
					'replacement' => '[attachment]/file/path.txt[/attachment]',
					'example' => '',
					'description' => $l->t('Attachment'),
					'parse_inner' => false,
					'is_builtin' => true,
					'special_handler' => 'attachment',
				],
			];

			foreach ($bbcodes as $bbcode) {
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_bbcodes')
					->values([
						'tag' => $qb->createNamedParameter($bbcode['tag']),
						'replacement' => $qb->createNamedParameter($bbcode['replacement']),
						'example' => $qb->createNamedParameter($bbcode['example']),
						'description' => $qb->createNamedParameter($bbcode['description']),
						'enabled' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'parse_inner' => $qb->createNamedParameter($bbcode['parse_inner'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'is_builtin' => $qb->createNamedParameter($bbcode['is_builtin'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						'special_handler' => $qb->createNamedParameter($bbcode['special_handler']),
						'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					])
					->executeStatement();
			}

			$db->commit();
			$logger->info('Forum seeding: Created default BBCodes');
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create default BBCodes', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Assign roles to all Nextcloud users
	 */
	public static function assignUserRoles(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$userManager = \OC::$server->get(\OCP\IUserManager::class);
		$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Check if user roles already exist
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_user_roles')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: User roles already assigned, skipping');
				return;
			}

			// Assign roles to all users
			$usersProcessed = 0;
			$userManager->callForAllUsers(function ($user) use ($db, $timestamp, $groupManager, $logger, &$usersProcessed) {
				try {
					$userId = $user->getUID();
					$isAdmin = $groupManager->isAdmin($userId);

					// Assign User role (ID 3) to all users
					$qb = $db->getQueryBuilder();
					$qb->insert('forum_user_roles')
						->values([
							'user_id' => $qb->createNamedParameter($userId),
							'role_id' => $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						])
						->executeStatement();

					// Assign Admin role (ID 1) to admin group members
					if ($isAdmin) {
						$qb = $db->getQueryBuilder();
						$qb->insert('forum_user_roles')
							->values([
								'user_id' => $qb->createNamedParameter($userId),
								'role_id' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
								'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							])
							->executeStatement();
					}

					$usersProcessed++;
					if ($usersProcessed % 100 === 0) {
						$logger->info("Forum seeding: Processed $usersProcessed users");
					}
				} catch (\Exception $e) {
					// Log error but continue with other users
					$logger->warning('Forum seeding: Failed to assign roles to user ' . $user->getUID(), [
						'exception' => $e->getMessage(),
					]);
				}
			});

			$logger->info("Forum seeding: Assigned roles to $usersProcessed users");
		} catch (\Exception $e) {
			$logger->error('Forum seeding: Failed to assign user roles', [
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Seed welcome thread
	 */
	public static function seedWelcomeThread(): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$userManager = \OC::$server->get(\OCP\IUserManager::class);
		$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Check if welcome thread already exists
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_threads')
				->where($qb->expr()->eq('slug', $qb->createNamedParameter('welcome-to-nextcloud-forums')));
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if ($exists) {
				$logger->info('Forum seeding: Welcome thread already exists, skipping');
				return;
			}

			// Get first category ID
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_categories')
				->orderBy('id', 'ASC')
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$category = $result->fetch();
			$result->closeCursor();

			if (!$category) {
				$logger->warning('Forum seeding: No categories found, skipping welcome thread creation');
				return;
			}

			$categoryId = (int)$category['id'];

			// Find first admin user (fallback to 'admin')
			$adminUserId = 'admin';
			$userManager->callForSeenUsers(function ($user) use ($groupManager, &$adminUserId) {
				if ($groupManager->isAdmin($user->getUID())) {
					$adminUserId = $user->getUID();
					return false; // Stop iteration
				}
			});

			$db->beginTransaction();

			// Create welcome thread
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_threads')
				->values([
					'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'author_id' => $qb->createNamedParameter($adminUserId),
					'title' => $qb->createNamedParameter($l->t('Welcome to Nextcloud Forums')),
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
			$welcomeContent = $l->t('Welcome to the Nextcloud Forums!') . "\n\n"
				. $l->t('This is a community-driven forum built right into your Nextcloud instance. '
				. 'Here you can discuss topics, share ideas, and collaborate with other users.') . "\n\n"
				. '[b]' . $l->t('Features:') . "[/b]\n"
				. "[list]\n"
				. '[*]' . $l->t('Create and reply to threads') . "\n"
				. '[*]' . $l->t('Organize discussions by categories') . "\n"
				. '[*]' . $l->t('Use BBCode for rich text formatting') . "\n"
				. '[*]' . $l->t('Attach files from your Nextcloud storage') . "\n"
				. '[*]' . $l->t('React to posts') . "\n"
				. '[*]' . $l->t('Track read/unread threads') . "\n\n"
				. "[/list]\n"
				. '[b]' . $l->t('BBCode Examples:') . "[/b]\n"
				. "[list]\n"
				. '[*][b]' . $l->t('Bold text') . '[/b] - ' . $l->t('Use [icode][b]text[/b][/icode]') . "\n"
				. '[*][i]' . $l->t('Italic text') . '[/i] - ' . $l->t('Use [icode][i]text[/i][/icode]') . "\n"
				. '[*][u]' . $l->t('Underlined text') . '[/u] - ' . $l->t('Use [icode][u]text[/u][/icode]') . "\n\n"
				. "[/list]\n"
				. $l->t('Feel free to start a new discussion or reply to existing threads. Happy posting!');

			$qb = $db->getQueryBuilder();
			$qb->insert('forum_posts')
				->values([
					'thread_id' => $qb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'author_id' => $qb->createNamedParameter($adminUserId),
					'content' => $qb->createNamedParameter($welcomeContent),
					'slug' => $qb->createNamedParameter('welcome-to-nextcloud-forums-1'),
					'is_edited' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
					'is_first_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
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

			// Update category counts
			// Note: post_count is 0 because the first post (is_first_post=true) doesn't count
			$qb = $db->getQueryBuilder();
			$qb->update('forum_categories')
				->set('thread_count', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->set('post_count', $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
				->executeStatement();

			// Create user stats for the admin user
			$qb = $db->getQueryBuilder();
			$qb->insert('forum_user_stats')
				->values([
					'user_id' => $qb->createNamedParameter($adminUserId),
					'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'thread_count' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'last_post_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				])
				->executeStatement();

			$db->commit();
			$logger->info('Forum seeding: Created welcome thread');
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create welcome thread', [
				'exception' => $e->getMessage(),
			]);
		}
	}
}
