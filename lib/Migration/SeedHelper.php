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
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedAll($output = null): void {
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$logger->info('Forum seeding: Starting data seed/repair');

		if ($output) {
			$output->info('Forum: Starting data seed/repair...');
		}

		// Ensure forum_users table exists (handle rename from forum_user_stats if needed)
		self::ensureForumUsersTable($output);

		// Each function checks its own state and returns early if already seeded
		// They run independently so one failure doesn't block others
		self::seedDefaultRoles($output);
		self::seedCategoryHeaders($output);
		self::seedDefaultCategories($output);
		self::seedCategoryPermissions($output);
		self::seedGuestRolePermissions($output);
		self::seedDefaultBBCodes($output);
		self::assignUserRoles($output);
		self::seedWelcomeThread($output);

		$logger->info('Forum seeding: Completed data seed/repair');

		if ($output) {
			$output->info('Forum: Data seed/repair completed');
		}
	}

	/**
	 * Public wrapper for ensureForumUsersTable for use in migrations
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function ensureForumUsersTablePublic($output = null): void {
		self::ensureForumUsersTable($output);
	}

	/**
	 * Ensure forum_users table exists, renaming from forum_user_stats if needed,
	 * or creating it from scratch for fresh installations.
	 * This handles cases where migrations partially failed or fresh installs.
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	private static function ensureForumUsersTable($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$config = \OC::$server->get(\OCP\IConfig::class);
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

		$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
		$oldTable = $prefix . 'forum_user_stats';
		$newTable = $prefix . 'forum_users';

		$oldTableExists = self::tableExists($db, $oldTable);
		$newTableExists = self::tableExists($db, $newTable);

		if ($oldTableExists && !$newTableExists) {
			// Case 1: Old table exists, rename it
			$logger->info('Forum seeding: Renaming forum_user_stats to forum_users...');
			if ($output) {
				$output->info('  → Renaming forum_user_stats to forum_users...');
			}

			try {
				$platform = $db->getDatabasePlatform();
				$platformName = $platform->getName();

				if ($platformName === 'mysql' || $platformName === 'mariadb') {
					$db->executeStatement("RENAME TABLE `{$oldTable}` TO `{$newTable}`");
				} else {
					// PostgreSQL, SQLite and others
					$db->executeStatement("ALTER TABLE \"{$oldTable}\" RENAME TO \"{$newTable}\"");
				}

				$logger->info('Forum seeding: Table renamed successfully');
				if ($output) {
					$output->info('  ✓ Table renamed successfully');
				}
			} catch (\Exception $e) {
				$logger->error('Forum seeding: Failed to rename table', ['exception' => $e->getMessage()]);
				throw $e;
			}
		} elseif (!$oldTableExists && !$newTableExists) {
			// Case 2: Neither table exists (fresh install), create forum_users
			$logger->info('Forum seeding: Creating forum_users table...');
			if ($output) {
				$output->info('  → Creating forum_users table...');
			}

			try {
				self::createForumUsersTable($db);

				$logger->info('Forum seeding: Table created successfully');
				if ($output) {
					$output->info('  ✓ Table created successfully');
				}
			} catch (\Exception $e) {
				$logger->error('Forum seeding: Failed to create forum_users table', ['exception' => $e->getMessage()]);
				throw $e;
			}
		}
		// Case 3: $newTableExists is true - nothing to do
	}

	/**
	 * Create the forum_users table from scratch
	 * This mirrors the final schema from Version1 + Version2 + Version8 migrations
	 * (id as primary key, user_id as unique, includes signature column)
	 */
	private static function createForumUsersTable(\OCP\IDBConnection $db): void {
		$platform = $db->getDatabasePlatform();
		$config = \OC::$server->get(\OCP\IConfig::class);
		$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
		$tableName = $prefix . 'forum_users';

		// Use instanceof checks for reliable platform detection (getName() is deprecated)
		if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform) {
			// MySQL and MariaDB both extend MySQLPlatform
			$db->executeStatement("
				CREATE TABLE `{$tableName}` (
					`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					`user_id` VARCHAR(64) NOT NULL,
					`post_count` INT UNSIGNED NOT NULL DEFAULT 0,
					`thread_count` INT UNSIGNED NOT NULL DEFAULT 0,
					`last_post_at` INT UNSIGNED DEFAULT NULL,
					`deleted_at` INT UNSIGNED DEFAULT NULL,
					`signature` TEXT DEFAULT NULL,
					`created_at` INT UNSIGNED NOT NULL,
					`updated_at` INT UNSIGNED NOT NULL,
					PRIMARY KEY (`id`),
					UNIQUE INDEX `forum_users_user_id_uniq` (`user_id`),
					INDEX `forum_users_post_count_idx` (`post_count`),
					INDEX `forum_users_thread_count_idx` (`thread_count`),
					INDEX `forum_users_deleted_at_idx` (`deleted_at`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
			");
		} elseif ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
			$db->executeStatement("
				CREATE TABLE \"{$tableName}\" (
					\"id\" BIGSERIAL PRIMARY KEY,
					\"user_id\" VARCHAR(64) NOT NULL,
					\"post_count\" INTEGER NOT NULL DEFAULT 0,
					\"thread_count\" INTEGER NOT NULL DEFAULT 0,
					\"last_post_at\" INTEGER DEFAULT NULL,
					\"deleted_at\" INTEGER DEFAULT NULL,
					\"signature\" TEXT DEFAULT NULL,
					\"created_at\" INTEGER NOT NULL,
					\"updated_at\" INTEGER NOT NULL
				)
			");
			$db->executeStatement("CREATE UNIQUE INDEX \"forum_users_user_id_uniq\" ON \"{$tableName}\" (\"user_id\")");
			$db->executeStatement("CREATE INDEX \"forum_users_post_count_idx\" ON \"{$tableName}\" (\"post_count\")");
			$db->executeStatement("CREATE INDEX \"forum_users_thread_count_idx\" ON \"{$tableName}\" (\"thread_count\")");
			$db->executeStatement("CREATE INDEX \"forum_users_deleted_at_idx\" ON \"{$tableName}\" (\"deleted_at\")");
		} else {
			// SQLite (and any other platform as fallback)
			$db->executeStatement("
				CREATE TABLE \"{$tableName}\" (
					\"id\" INTEGER PRIMARY KEY AUTOINCREMENT,
					\"user_id\" VARCHAR(64) NOT NULL,
					\"post_count\" INTEGER NOT NULL DEFAULT 0,
					\"thread_count\" INTEGER NOT NULL DEFAULT 0,
					\"last_post_at\" INTEGER DEFAULT NULL,
					\"deleted_at\" INTEGER DEFAULT NULL,
					\"signature\" TEXT DEFAULT NULL,
					\"created_at\" INTEGER NOT NULL,
					\"updated_at\" INTEGER NOT NULL
				)
			");
			$db->executeStatement("CREATE UNIQUE INDEX \"forum_users_user_id_uniq\" ON \"{$tableName}\" (\"user_id\")");
			$db->executeStatement("CREATE INDEX \"forum_users_post_count_idx\" ON \"{$tableName}\" (\"post_count\")");
			$db->executeStatement("CREATE INDEX \"forum_users_thread_count_idx\" ON \"{$tableName}\" (\"thread_count\")");
			$db->executeStatement("CREATE INDEX \"forum_users_deleted_at_idx\" ON \"{$tableName}\" (\"deleted_at\")");
		}
	}

	/**
	 * Check if a table exists in the database
	 */
	private static function tableExists(\OCP\IDBConnection $db, string $tableName): bool {
		$platform = $db->getDatabasePlatform();
		$platformName = $platform->getName();

		try {
			if ($platformName === 'mysql' || $platformName === 'mariadb') {
				$result = $db->executeQuery(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
					[$tableName]
				);
			} elseif ($platformName === 'postgresql') {
				$result = $db->executeQuery(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = ?',
					[$tableName]
				);
			} else {
				// SQLite
				$result = $db->executeQuery(
					"SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = ?",
					[$tableName]
				);
			}

			$count = (int)$result->fetchOne();
			$result->closeCursor();
			return $count > 0;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Seed default roles (Admin, Moderator, User, Guest)
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedDefaultRoles($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Get existing roles by role_type (not hardcoded IDs) to check what needs to be created
			$qb = $db->getQueryBuilder();
			$qb->select('role_type')
				->from('forum_roles')
				->where($qb->expr()->in('role_type', $qb->createNamedParameter([
					\OCA\Forum\Db\Role::ROLE_TYPE_ADMIN,
					\OCA\Forum\Db\Role::ROLE_TYPE_MODERATOR,
					\OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT,
					\OCA\Forum\Db\Role::ROLE_TYPE_GUEST,
				], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)));
			$result = $qb->executeQuery();
			$existingRoles = $result->fetchAll();
			$result->closeCursor();

			// Use array_unique to handle duplicates (shouldn't happen after cleanup migration, but be defensive)
			$existingTypes = array_unique(array_map(fn ($role) => $role['role_type'], $existingRoles));

			if (count($existingTypes) === 4) {
				$logger->info('Forum seeding: Default roles already exist, skipping');
				if ($output) {
					$output->info('  ✓ Default roles already exist');
				}
				return;
			}

			if ($output) {
				$output->info('  → Creating default roles...');
			}

			$db->beginTransaction();
			$rolesCreated = 0;

			// Define roles by role_type (not hardcoded IDs)
			$rolesToCreate = [
				\OCA\Forum\Db\Role::ROLE_TYPE_ADMIN => [
					'name' => $l->t('Admin'),
					'description' => $l->t('Administrator role with full permissions'),
					'can_access_admin_tools' => true,
					'can_edit_roles' => true,
					'can_edit_categories' => true,
					'is_system_role' => true,
					'role_type' => \OCA\Forum\Db\Role::ROLE_TYPE_ADMIN,
				],
				\OCA\Forum\Db\Role::ROLE_TYPE_MODERATOR => [
					'name' => $l->t('Moderator'),
					'description' => $l->t('Moderator role with elevated permissions'),
					'can_access_admin_tools' => true,
					'can_edit_roles' => false,
					'can_edit_categories' => false,
					'is_system_role' => true,
					'role_type' => \OCA\Forum\Db\Role::ROLE_TYPE_MODERATOR,
				],
				\OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT => [
					'name' => $l->t('User'),
					'description' => $l->t('Default user role with basic permissions'),
					'can_access_admin_tools' => false,
					'can_edit_roles' => false,
					'can_edit_categories' => false,
					'is_system_role' => true,
					'role_type' => \OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT,
				],
				\OCA\Forum\Db\Role::ROLE_TYPE_GUEST => [
					'name' => $l->t('Guest'),
					'description' => $l->t('Guest role for unauthenticated users with read-only access'),
					'can_access_admin_tools' => false,
					'can_edit_roles' => false,
					'can_edit_categories' => false,
					'is_system_role' => true,
					'role_type' => \OCA\Forum\Db\Role::ROLE_TYPE_GUEST,
				],
			];

			foreach ($rolesToCreate as $roleType => $roleData) {
				if (!in_array($roleType, $existingTypes)) {
					$qb = $db->getQueryBuilder();
					$qb->insert('forum_roles')
						->values([
							'name' => $qb->createNamedParameter($roleData['name']),
							'description' => $qb->createNamedParameter($roleData['description']),
							'can_access_admin_tools' => $qb->createNamedParameter($roleData['can_access_admin_tools'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'can_edit_roles' => $qb->createNamedParameter($roleData['can_edit_roles'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'can_edit_categories' => $qb->createNamedParameter($roleData['can_edit_categories'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'is_system_role' => $qb->createNamedParameter($roleData['is_system_role'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'role_type' => $qb->createNamedParameter($roleData['role_type'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR),
							'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						])
						->executeStatement();
					$rolesCreated++;
					$logger->info("Forum seeding: Created role with type '$roleType'");
				}
			}

			$db->commit();

			// Validate that critical roles can be found by role_type after creation
			// Note: We query directly instead of using RoleMapper to avoid MultipleObjectsReturnedException
			// if duplicates somehow exist (the cleanup migration should have removed them, but be defensive)
			$criticalRoles = [
				\OCA\Forum\Db\Role::ROLE_TYPE_GUEST => 'Guest',
				\OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT => 'Default User',
				\OCA\Forum\Db\Role::ROLE_TYPE_ADMIN => 'Admin',
			];

			foreach ($criticalRoles as $roleType => $roleName) {
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_roles')
					->where($qb->expr()->eq('role_type', $qb->createNamedParameter($roleType, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)))
					->setMaxResults(1);
				$result = $qb->executeQuery();
				$role = $result->fetch();
				$result->closeCursor();

				if ($role) {
					$logger->info("Forum seeding: Validated $roleName role (ID {$role['id']}, type: $roleType)");
				} else {
					$logger->error("Forum seeding: CRITICAL - $roleName role not found after creation. This will break functionality.");
					if ($output) {
						$output->warning("  ✗ CRITICAL: $roleName role not found - forum may not function correctly");
					}
				}
			}

			$logger->info("Forum seeding: Created $rolesCreated default roles");
			if ($output) {
				$output->info("  ✓ Created $rolesCreated default roles (Admin, Moderator, User, Guest)");
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create default roles', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to create default roles: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to create default roles: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Seed guest role permissions (copy view permissions from User role)
	 * Note: Guest role must be created first in seedDefaultRoles()
	 * Note: Category permissions must exist first from seedCategoryPermissions()
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedGuestRolePermissions($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

		try {
			// Find the Guest role
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter(\OCA\Forum\Db\Role::ROLE_TYPE_GUEST, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)));
			$result = $qb->executeQuery();
			$guestRole = $result->fetch();
			$result->closeCursor();

			if (!$guestRole) {
				$logger->warning('Forum seeding: Guest role not found, cannot seed permissions');
				if ($output) {
					$output->warning('  ⚠ Guest role not found, skipping permission seeding');
				}
				return;
			}

			$guestRoleId = (int)$guestRole['id'];

			// Find the User (default) role ID
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter(\OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)));
			$result = $qb->executeQuery();
			$userRole = $result->fetch();
			$result->closeCursor();

			if (!$userRole) {
				$logger->warning('Forum seeding: User (default) role not found, cannot determine which categories to grant guest access to');
				if ($output) {
					$output->warning('  ⚠ User role not found, cannot seed guest permissions');
				}
				return;
			}

			$userRoleId = (int)$userRole['id'];

			// Check if guest role already has permissions (idempotency check)
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_category_perms')
				->where($qb->expr()->eq('role_id', $qb->createNamedParameter($guestRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$hasPermissions = $result->fetch();
			$result->closeCursor();

			if ($hasPermissions) {
				$logger->info('Forum seeding: Guest role permissions already exist, skipping');
				if ($output) {
					$output->info('  ✓ Guest role permissions already exist');
				}
				return;
			}

			if ($output) {
				$output->info('  → Setting guest role permissions...');
			}

			// Get only categories where the User role has view permission
			$qb = $db->getQueryBuilder();
			$qb->select('category_id')
				->from('forum_category_perms')
				->where($qb->expr()->eq('role_id', $qb->createNamedParameter($userRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('can_view', $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL)));
			$result = $qb->executeQuery();
			$userAccessibleCategories = $result->fetchAll();
			$result->closeCursor();

			$db->beginTransaction();
			$categoriesGranted = 0;
			foreach ($userAccessibleCategories as $categoryRow) {
				$categoryId = (int)$categoryRow['category_id'];

				// Check if permission already exists
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_category_perms')
					->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($guestRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
				$result = $qb->executeQuery();
				$permExists = $result->fetch();
				$result->closeCursor();

				if (!$permExists) {
					$qb = $db->getQueryBuilder();
					$qb->insert('forum_category_perms')
						->values([
							'category_id' => $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							'role_id' => $qb->createNamedParameter($guestRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							'can_view' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'can_post' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'can_reply' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
							'can_moderate' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
						])
						->executeStatement();
					$categoriesGranted++;
				}
			}

			$db->commit();
			$logger->info('Forum seeding: Set guest role view-only permissions for ' . $categoriesGranted . ' categories (matching User role access)');
			if ($output) {
				$output->info('  ✓ Set guest role view-only permissions for ' . $categoriesGranted . ' categories');
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to set guest role permissions', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to set guest role permissions: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to set guest role permissions: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Seed category headers
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedCategoryHeaders($output = null): void {
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
				if ($output) {
					$output->info('  ✓ Category headers already exist');
				}
				return;
			}

			if ($output) {
				$output->info('  → Creating category headers...');
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
			if ($output) {
				$output->info('  ✓ Created category headers');
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create category headers', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to create category headers: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to create category headers: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Seed default categories
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedDefaultCategories($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
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
				$logger->error('Forum seeding: No category headers found, cannot create categories');
				if ($output) {
					$output->warning('  ✗ No category headers found, cannot create categories');
				}
				throw new \RuntimeException('Cannot create categories: category headers must be created first');
			}

			if ($output) {
				$output->info('  → Creating default categories...');
			}

			$headerId = (int)$header['id'];
			$db->beginTransaction();
			$categoriesCreated = 0;

			// Check if "General Discussions" category exists
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_categories')
				->where($qb->expr()->eq('slug', $qb->createNamedParameter('general-discussions')));
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if (!$exists) {
				// Create "General Discussions" category
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_categories')
					->values([
						'header_id' => $qb->createNamedParameter($headerId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'name' => $qb->createNamedParameter($l->t('General discussions')),
						'description' => $qb->createNamedParameter($l->t('A place for general conversations and discussions')),
						'slug' => $qb->createNamedParameter('general-discussions'),
						'sort_order' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'thread_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					])
					->executeStatement();
				$categoriesCreated++;
			}

			// Check if "Support" category exists
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_categories')
				->where($qb->expr()->eq('slug', $qb->createNamedParameter('support')));
			$result = $qb->executeQuery();
			$exists = $result->fetch();
			$result->closeCursor();

			if (!$exists) {
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
				$categoriesCreated++;
			}

			$db->commit();
			$logger->info("Forum seeding: Created $categoriesCreated default categories");
			if ($output) {
				$output->info("  ✓ Created $categoriesCreated default categories (General Discussions, Support)");
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create default categories', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to create default categories: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to create default categories: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Seed category permissions for all roles
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedCategoryPermissions($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

		try {
			// Get all category IDs
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_categories');
			$result = $qb->executeQuery();
			$categories = $result->fetchAll();
			$result->closeCursor();

			if (empty($categories)) {
				$logger->error('Forum seeding: No categories found, cannot create permissions');
				if ($output) {
					$output->warning('  ✗ No categories found, cannot create permissions');
				}
				throw new \RuntimeException('Cannot create category permissions: categories must be created first');
			}

			// Find Moderator role by role_type (not hardcoded ID)
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter(\OCA\Forum\Db\Role::ROLE_TYPE_MODERATOR, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)));
			$result = $qb->executeQuery();
			$moderatorRole = $result->fetch();
			$result->closeCursor();

			// Find User (default) role by role_type (not hardcoded ID)
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter(\OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)));
			$result = $qb->executeQuery();
			$userRole = $result->fetch();
			$result->closeCursor();

			if (!$moderatorRole || !$userRole) {
				$logger->error('Forum seeding: Not all required roles exist, cannot create permissions');
				if ($output) {
					$output->warning('  ✗ Required roles do not exist, cannot create permissions');
				}
				throw new \RuntimeException('Cannot create category permissions: roles must be created first');
			}

			$moderatorRoleId = (int)$moderatorRole['id'];
			$userRoleId = (int)$userRole['id'];

			if ($output) {
				$output->info('  → Creating category permissions...');
			}

			$db->beginTransaction();
			$permissionsCreated = 0;

			// Create permissions for Moderator and User roles (Admin has implicit permissions)
			foreach ($categories as $category) {
				$categoryId = (int)$category['id'];

				// Check and create Moderator role permissions
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_category_perms')
					->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($moderatorRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
				$result = $qb->executeQuery();
				$exists = $result->fetch();
				$result->closeCursor();

				if (!$exists) {
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
					$permissionsCreated++;
				}

				// Check and create User role permissions
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_category_perms')
					->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($userRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
				$result = $qb->executeQuery();
				$exists = $result->fetch();
				$result->closeCursor();

				if (!$exists) {
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
					$permissionsCreated++;
				}
			}

			$db->commit();
			$logger->info("Forum seeding: Created $permissionsCreated category permissions");
			if ($output) {
				$output->info("  ✓ Created $permissionsCreated category permissions for " . count($categories) . ' categories');
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create category permissions', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to create category permissions: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to create category permissions: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Seed default BBCodes
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedDefaultBBCodes($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$l = \OC::$server->getL10N('forum');
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			if ($output) {
				$output->info('  → Creating default BBCodes...');
			}

			$db->beginTransaction();

			$bbcodes = [
				[
					'tag' => 'icode',
					'replacement' => '<code>{content}</code>',
					'example' => '[icode]' . $l->t('Inline code') . '[/icode]',
					'description' => $l->t('Inline code'),
					'parse_inner' => false,
					'is_builtin' => true,
					'special_handler' => null,
				],
				[
					'tag' => 'spoiler',
					'replacement' => '<details><summary>{title}</summary>{content}</details>',
					'example' => '[spoiler="' . $l->t('Spoiler title') . '"]' . $l->t('Hidden content') . '[/spoiler]',
					'description' => $l->t('Spoilers'),
					'parse_inner' => false,
					'is_builtin' => true,
					'special_handler' => null,
				],
				[
					'tag' => 'attachment',
					'replacement' => '{content}',
					'example' => '[attachment]/file/path.txt[/attachment]',
					'description' => $l->t('Attachment'),
					'parse_inner' => false,
					'is_builtin' => true,
					'special_handler' => 'attachment',
				],
			];

			$bbcodesCreated = 0;
			foreach ($bbcodes as $bbcode) {
				// Check if this specific BBCode already exists
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_bbcodes')
					->where($qb->expr()->eq('tag', $qb->createNamedParameter($bbcode['tag'])));
				$result = $qb->executeQuery();
				$exists = $result->fetch();
				$result->closeCursor();

				if (!$exists) {
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
					$bbcodesCreated++;
				}
			}

			$db->commit();
			$logger->info("Forum seeding: Created $bbcodesCreated default BBCodes");
			if ($output) {
				$output->info("  ✓ Created $bbcodesCreated default BBCodes (icode, spoiler, attachment)");
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create default BBCodes', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to create default BBCodes: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to create default BBCodes: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Assign roles to all Nextcloud users
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function assignUserRoles($output = null): void {
		$db = \OC::$server->get(\OCP\IDBConnection::class);
		$userManager = \OC::$server->get(\OCP\IUserManager::class);
		$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
		$timestamp = time();

		try {
			// Find Admin role by role_type (not hardcoded ID)
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter(\OCA\Forum\Db\Role::ROLE_TYPE_ADMIN, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)));
			$result = $qb->executeQuery();
			$adminRole = $result->fetch();
			$result->closeCursor();

			// Find User (default) role by role_type (not hardcoded ID)
			$qb = $db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->where($qb->expr()->eq('role_type', $qb->createNamedParameter(\OCA\Forum\Db\Role::ROLE_TYPE_DEFAULT, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR)));
			$result = $qb->executeQuery();
			$userRole = $result->fetch();
			$result->closeCursor();

			if (!$adminRole || !$userRole) {
				$logger->error('Forum seeding: Required roles do not exist, cannot assign user roles');
				if ($output) {
					$output->warning('  ✗ Required roles do not exist, cannot assign user roles');
				}
				throw new \RuntimeException('Cannot assign user roles: roles must be created first');
			}

			$adminRoleId = (int)$adminRole['id'];
			$userRoleId = (int)$userRole['id'];

			if ($output) {
				$output->info('  → Assigning roles to users...');
			}

			// Assign roles to all users
			$usersProcessed = 0;
			$usersSkipped = 0;
			$userManager->callForAllUsers(function ($user) use ($db, $timestamp, $groupManager, $logger, $output, &$usersProcessed, &$usersSkipped, $adminRoleId, $userRoleId) {
				try {
					$userId = $user->getUID();
					$isAdmin = $groupManager->isAdmin($userId);

					// Check if user already has the User role
					$qb = $db->getQueryBuilder();
					$qb->select('id')
						->from('forum_user_roles')
						->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
						->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($userRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
					$result = $qb->executeQuery();
					$hasUserRole = $result->fetch();
					$result->closeCursor();

					// Assign User role to all users if they do not have it
					if (!$hasUserRole) {
						$qb = $db->getQueryBuilder();
						$qb->insert('forum_user_roles')
							->values([
								'user_id' => $qb->createNamedParameter($userId),
								'role_id' => $qb->createNamedParameter($userRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
								'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							])
							->executeStatement();
					}

					// Check if admin user already has the Admin role
					if ($isAdmin) {
						$qb = $db->getQueryBuilder();
						$qb->select('id')
							->from('forum_user_roles')
							->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
							->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
						$result = $qb->executeQuery();
						$hasAdminRole = $result->fetch();
						$result->closeCursor();

						// Assign Admin role to admin group members if they do not have it
						if (!$hasAdminRole) {
							$qb = $db->getQueryBuilder();
							$qb->insert('forum_user_roles')
								->values([
									'user_id' => $qb->createNamedParameter($userId),
									'role_id' => $qb->createNamedParameter($adminRoleId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
									'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
								])
								->executeStatement();
						}
					}

					$usersProcessed++;
					if ($usersProcessed % 100 === 0) {
						$logger->info("Forum seeding: Processed $usersProcessed users");
						if ($output) {
							$output->info("    → Processed $usersProcessed users...");
						}
					}
				} catch (\Exception $e) {
					// Log error but continue with other users
					$logger->warning('Forum seeding: Failed to assign roles to user ' . $user->getUID(), [
						'exception' => $e->getMessage(),
					]);
					$usersSkipped++;
				}
			});

			$logger->info("Forum seeding: Assigned roles to $usersProcessed users" . ($usersSkipped > 0 ? " ($usersSkipped skipped due to errors)" : ''));
			if ($output) {
				$output->info("  ✓ Assigned roles to $usersProcessed users" . ($usersSkipped > 0 ? " ($usersSkipped skipped)" : ''));
			}
		} catch (\Exception $e) {
			$logger->error('Forum seeding: Failed to assign user roles', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to assign user roles: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to assign user roles: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Seed welcome thread
	 *
	 * @param \OCP\Migration\IOutput|null $output Optional output for console messages
	 */
	public static function seedWelcomeThread($output = null): void {
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
				if ($output) {
					$output->info('  ✓ Welcome thread already exists');
				}
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
				$logger->error('Forum seeding: No categories found, cannot create welcome thread');
				if ($output) {
					$output->warning('  ✗ No categories found, cannot create welcome thread');
				}
				throw new \RuntimeException('Cannot create welcome thread: categories must be created first');
			}

			if ($output) {
				$output->info('  → Creating welcome thread...');
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
					'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
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
				. 'Here you can discuss topics, share ideas and collaborate with other users.') . "\n\n"
				. '[b]' . $l->t('Features:') . "[/b]\n"
				. "[list]\n"
				. '[*]' . $l->t('Create and reply to threads') . "\n"
				. '[*]' . $l->t('Organize discussions by categories') . "\n"
				. '[*]' . $l->t('Use BBCode for rich text formatting') . "\n"
				. '[*]' . $l->t('Attach files from your Nextcloud storage') . "\n"
				. '[*]' . $l->t('React to posts') . "\n"
				. '[*]' . $l->t('Track read/unread threads') . "\n\n"
				. "[/list]\n"
				. '[b]' . $l->t('BBCode examples:') . "[/b]\n"
				. "[list]\n"
				. '[*][b]' . $l->t('Bold text') . '[/b] - ' . $l->t('Use %1$stext%2$s', ['[icode][b]', '[/b][/icode]']) . "\n"
				. '[*][i]' . $l->t('Italic text') . '[/i] - ' . $l->t('Use %1$stext%2$s', ['[icode][i]', '[/i][/icode]']) . "\n"
				. '[*][u]' . $l->t('Underlined text') . '[/u] - ' . $l->t('Use %1$stext%2$s', ['[icode][u]', '[/u][/icode]']) . "\n\n"
				. "[/list]\n"
				. $l->t('Feel free to start a new discussion or reply to existing threads. Happy posting!');

			// Check if slug column still exists (for backwards compatibility with old migrations)
			// Use a query to check column existence since schema introspection APIs vary
			$hasSlugColumn = true;
			try {
				$checkQb = $db->getQueryBuilder();
				$checkQb->select('slug')->from('forum_posts')->setMaxResults(1);
				$checkQb->executeQuery()->closeCursor();
			} catch (\Exception $e) {
				$hasSlugColumn = false;
			}

			// Build post values - slug is optional (removed in Version8)
			$qb = $db->getQueryBuilder();
			$postValues = [
				'thread_id' => $qb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'author_id' => $qb->createNamedParameter($adminUserId),
				'content' => $qb->createNamedParameter($welcomeContent),
				'is_edited' => $qb->createNamedParameter(false, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'is_first_post' => $qb->createNamedParameter(true, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_BOOL),
				'edited_at' => $qb->createNamedParameter(null, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
			];
			if ($hasSlugColumn) {
				$postValues['slug'] = $qb->createNamedParameter('welcome-to-nextcloud-forums-1');
			}

			$qb->insert('forum_posts')
				->values($postValues)
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

			// Subscribe the admin user to the welcome thread
			if ($db->tableExists('forum_thread_subs')) {
				// Check if subscription already exists
				$qb = $db->getQueryBuilder();
				$qb->select('id')
					->from('forum_thread_subs')
					->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($adminUserId)));
				$result = $qb->executeQuery();
				$subExists = $result->fetch();
				$result->closeCursor();

				if (!$subExists) {
					$qb = $db->getQueryBuilder();
					$qb->insert('forum_thread_subs')
						->values([
							'thread_id' => $qb->createNamedParameter($threadId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
							'user_id' => $qb->createNamedParameter($adminUserId),
							'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						])
						->executeStatement();
				}
			}

			// Create forum user for the admin user if it does not exist
			$qb = $db->getQueryBuilder();
			$qb->select('user_id')
				->from('forum_users')
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($adminUserId)));
			$result = $qb->executeQuery();
			$statsExists = $result->fetch();
			$result->closeCursor();

			if (!$statsExists) {
				$qb = $db->getQueryBuilder();
				$qb->insert('forum_users')
					->values([
						'user_id' => $qb->createNamedParameter($adminUserId),
						'post_count' => $qb->createNamedParameter(0, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'thread_count' => $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'last_post_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'created_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'updated_at' => $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
					])
					->executeStatement();
			} else {
				// Update existing stats to increment thread count
				$qb = $db->getQueryBuilder();
				$qb->update('forum_users')
					->set('thread_count', $qb->func()->add('thread_count', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
					->set('last_post_at', $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
					->set('updated_at', $qb->createNamedParameter($timestamp, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
					->where($qb->expr()->eq('user_id', $qb->createNamedParameter($adminUserId)))
					->executeStatement();
			}

			$db->commit();
			$logger->info('Forum seeding: Created welcome thread');
			if ($output) {
				$output->info('  ✓ Created welcome thread');
			}
		} catch (\Exception $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			$logger->error('Forum seeding: Failed to create welcome thread', [
				'exception' => $e->getMessage(),
			]);
			if ($output) {
				$output->warning('  ✗ Failed to create welcome thread: ' . $e->getMessage());
			}
			throw new \RuntimeException('Failed to create welcome thread: ' . $e->getMessage(), 0, $e);
		}
	}
}
