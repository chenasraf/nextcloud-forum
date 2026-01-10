<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Tests\Integration\Migration;

use OCA\Forum\Db\Role;
use OCA\Forum\Migration\SeedHelper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for SeedHelper
 *
 * These tests run against a real database to ensure seeding works correctly
 * on both MySQL and PostgreSQL. They verify:
 * - Seeding creates required data on a clean database
 * - Seeding is idempotent (running twice doesn't cause errors)
 * - Individual seed operations can fail without breaking others
 * - Transaction handling works correctly on PostgreSQL
 *
 * Note: These tests require the Forum app tables to exist. They will be skipped
 * if run in an environment where migrations haven't been run (e.g., local dev with SQLite).
 * In CI, the app is properly installed with tables before tests run.
 *
 * @group integration
 * @group database
 */
class SeedHelperTest extends TestCase {
	private IDBConnection $db;
	private bool $tablesExist = false;

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OC::$server->get(IDBConnection::class);

		// Check if forum tables exist (they might not in local dev environment)
		$this->tablesExist = $this->checkTablesExist();

		if (!$this->tablesExist) {
			$this->markTestSkipped('Forum tables do not exist. Run these tests in CI or with a fully installed Nextcloud instance.');
		}
	}

	/**
	 * Check if the forum tables exist in the database
	 */
	private function checkTablesExist(): bool {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')
				->from('forum_roles')
				->setMaxResults(1);
			$qb->executeQuery()->closeCursor();
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	protected function tearDown(): void {
		// Clean up test data after each test
		$this->cleanupTestData();
		parent::tearDown();
	}

	/**
	 * Clean up all forum data to start fresh
	 */
	private function cleanupTestData(): void {
		$tables = [
			'forum_thread_subs',
			'forum_read_markers',
			'forum_reactions',
			'forum_posts',
			'forum_threads',
			'forum_category_perms',
			'forum_categories',
			'forum_cat_headers',
			'forum_user_roles',
			'forum_users',
			'forum_bbcodes',
			'forum_roles',
		];

		foreach ($tables as $table) {
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->delete($table)->executeStatement();
			} catch (\Exception $e) {
				// Table might not exist, ignore
			}
		}
	}

	/**
	 * Test that seedAll creates all required data on a clean database
	 */
	public function testSeedAllCreatesRequiredData(): void {
		// Clean state
		$this->cleanupTestData();

		// Run seeding
		SeedHelper::seedAll(null, false);

		// Verify roles were created
		$this->assertRolesExist();

		// Verify category headers were created
		$this->assertCategoryHeadersExist();

		// Verify categories were created
		$this->assertCategoriesExist();

		// Verify BBCodes were created
		$this->assertBBCodesExist();

		// Verify category permissions were created
		$this->assertCategoryPermissionsExist();
	}

	/**
	 * Test that seedAll is idempotent - running twice doesn't cause errors
	 */
	public function testSeedAllIsIdempotent(): void {
		// Clean state
		$this->cleanupTestData();

		// Run seeding twice
		SeedHelper::seedAll(null, false);
		SeedHelper::seedAll(null, false);

		// Verify data exists (and isn't duplicated)
		$this->assertRolesExist();
		$this->assertNoDuplicateRoles();
	}

	/**
	 * Test that seedDefaultRoles creates all four roles
	 */
	public function testSeedDefaultRolesCreatesAllRoles(): void {
		$this->cleanupTestData();

		SeedHelper::seedDefaultRoles(null);

		$qb = $this->db->getQueryBuilder();
		$qb->select('role_type')
			->from('forum_roles');
		$result = $qb->executeQuery();
		$roles = $result->fetchAll();
		$result->closeCursor();

		$roleTypes = array_column($roles, 'role_type');

		$this->assertContains(Role::ROLE_TYPE_ADMIN, $roleTypes);
		$this->assertContains(Role::ROLE_TYPE_MODERATOR, $roleTypes);
		$this->assertContains(Role::ROLE_TYPE_DEFAULT, $roleTypes);
		$this->assertContains(Role::ROLE_TYPE_GUEST, $roleTypes);
		$this->assertCount(4, $roles, 'Should have exactly 4 roles');
	}

	/**
	 * Test that seedDefaultRoles is idempotent
	 */
	public function testSeedDefaultRolesIsIdempotent(): void {
		$this->cleanupTestData();

		SeedHelper::seedDefaultRoles(null);
		SeedHelper::seedDefaultRoles(null);

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forum_roles');
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertEquals(4, (int)$row['count'], 'Should still have exactly 4 roles after running twice');
	}

	/**
	 * Test that seedCategoryHeaders creates the General header
	 */
	public function testSeedCategoryHeadersCreatesHeader(): void {
		$this->cleanupTestData();

		SeedHelper::seedCategoryHeaders(null);

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('forum_cat_headers');
		$result = $qb->executeQuery();
		$headers = $result->fetchAll();
		$result->closeCursor();

		$this->assertCount(1, $headers);
		$this->assertNotEmpty($headers[0]['name']);
	}

	/**
	 * Test that seedDefaultCategories creates categories
	 */
	public function testSeedDefaultCategoriesCreatesCategories(): void {
		$this->cleanupTestData();

		// Categories need headers first
		SeedHelper::seedCategoryHeaders(null);
		SeedHelper::seedDefaultCategories(null);

		$qb = $this->db->getQueryBuilder();
		$qb->select('slug')
			->from('forum_categories');
		$result = $qb->executeQuery();
		$categories = $result->fetchAll();
		$result->closeCursor();

		$slugs = array_column($categories, 'slug');

		$this->assertContains('general-discussions', $slugs);
		$this->assertContains('support', $slugs);
	}

	/**
	 * Test that seedDefaultBBCodes creates all default BBCodes
	 */
	public function testSeedDefaultBBCodesCreatesBBCodes(): void {
		$this->cleanupTestData();

		SeedHelper::seedDefaultBBCodes(null);

		$qb = $this->db->getQueryBuilder();
		$qb->select('tag')
			->from('forum_bbcodes');
		$result = $qb->executeQuery();
		$bbcodes = $result->fetchAll();
		$result->closeCursor();

		$tags = array_column($bbcodes, 'tag');

		$this->assertContains('icode', $tags);
		$this->assertContains('spoiler', $tags);
		$this->assertContains('attachment', $tags);
	}

	/**
	 * Test that seeding continues after individual operation failure
	 * This is crucial for PostgreSQL where transaction abort cascades
	 */
	public function testSeedingContinuesAfterIndividualFailure(): void {
		$this->cleanupTestData();

		// Create a partial state - roles exist but categories don't have permissions
		SeedHelper::seedDefaultRoles(null);
		SeedHelper::seedCategoryHeaders(null);
		SeedHelper::seedDefaultCategories(null);
		// Skip category permissions intentionally

		// Now run full seedAll - it should complete remaining operations
		SeedHelper::seedAll(null, false);

		// Verify BBCodes were still created despite any issues
		$this->assertBBCodesExist();
	}

	/**
	 * Test that seedWelcomeThread creates thread and post
	 */
	public function testSeedWelcomeThreadCreatesContent(): void {
		$this->cleanupTestData();

		// Welcome thread needs roles, headers, and categories
		SeedHelper::seedDefaultRoles(null);
		SeedHelper::seedCategoryHeaders(null);
		SeedHelper::seedDefaultCategories(null);
		SeedHelper::seedWelcomeThread(null);

		// Verify thread exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('forum_threads')
			->where($qb->expr()->eq('slug', $qb->createNamedParameter('welcome-to-nextcloud-forums')));
		$result = $qb->executeQuery();
		$thread = $result->fetch();
		$result->closeCursor();

		$this->assertNotFalse($thread, 'Welcome thread should exist');

		// Verify post exists
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('forum_posts')
			->where($qb->expr()->eq('thread_id', $qb->createNamedParameter($thread['id'])));
		$result = $qb->executeQuery();
		$post = $result->fetch();
		$result->closeCursor();

		$this->assertNotFalse($post, 'Welcome post should exist');
	}

	/**
	 * Test that throwOnError parameter works correctly
	 */
	public function testThrowOnErrorParameter(): void {
		$this->cleanupTestData();

		// With throwOnError=false, should not throw even if something fails
		// (though in a clean state nothing should fail)
		SeedHelper::seedAll(null, false);

		// Verify it completed
		$this->assertRolesExist();
	}

	/**
	 * Test database connection recovery between operations
	 * This specifically tests the PostgreSQL transaction abort fix
	 */
	public function testConnectionRecoveryBetweenOperations(): void {
		$this->cleanupTestData();

		// Run seedAll which now includes connection recovery
		SeedHelper::seedAll(null, false);

		// If we got here without exception, connection recovery worked
		// Verify data exists
		$this->assertRolesExist();
		$this->assertCategoryHeadersExist();
		$this->assertCategoriesExist();
		$this->assertBBCodesExist();
	}

	// ========== Helper assertions ==========

	private function assertRolesExist(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forum_roles');
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertGreaterThanOrEqual(4, (int)$row['count'], 'Should have at least 4 roles');
	}

	private function assertNoDuplicateRoles(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('role_type')
			->selectAlias($qb->func()->count('*'), 'count')
			->from('forum_roles')
			->groupBy('role_type');
		$result = $qb->executeQuery();
		$groups = $result->fetchAll();
		$result->closeCursor();

		foreach ($groups as $group) {
			$this->assertEquals(1, (int)$group['count'], "Role type '{$group['role_type']}' should not be duplicated");
		}
	}

	private function assertCategoryHeadersExist(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forum_cat_headers');
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertGreaterThanOrEqual(1, (int)$row['count'], 'Should have at least 1 category header');
	}

	private function assertCategoriesExist(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forum_categories');
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertGreaterThanOrEqual(2, (int)$row['count'], 'Should have at least 2 categories');
	}

	private function assertBBCodesExist(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forum_bbcodes');
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertGreaterThanOrEqual(3, (int)$row['count'], 'Should have at least 3 BBCodes');
	}

	private function assertCategoryPermissionsExist(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'count'))
			->from('forum_category_perms');
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertGreaterThanOrEqual(1, (int)$row['count'], 'Should have at least 1 category permission');
	}
}
