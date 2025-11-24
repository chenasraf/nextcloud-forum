<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\CategoryPerm;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserRole;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PermissionServiceTest extends TestCase {
	private PermissionService $service;
	private UserRoleMapper $userRoleMapper;
	private RoleMapper $roleMapper;
	private CategoryPermMapper $categoryPermMapper;
	private CategoryMapper $categoryMapper;
	private ThreadMapper $threadMapper;
	private PostMapper $postMapper;
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->userRoleMapper = $this->createMock(UserRoleMapper::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->categoryPermMapper = $this->createMock(CategoryPermMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new PermissionService(
			$this->userRoleMapper,
			$this->roleMapper,
			$this->categoryPermMapper,
			$this->categoryMapper,
			$this->threadMapper,
			$this->postMapper,
			$this->logger
		);
	}

	public function testHasGlobalPermissionReturnsTrueWhenUserHasPermission(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$role = $this->createRole(1, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertTrue($result);
	}

	public function testHasGlobalPermissionReturnsFalseWhenUserLacksPermission(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$role = $this->createRole(1, 'User', false, false, false, false, Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasGlobalPermissionReturnsFalseWhenUserHasNoRoles(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([]);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasGlobalPermissionChecksMultipleRoles(): void {
		$userId = 'user1';
		$permission = 'canEditCategories';

		$role1 = $this->createRole(1, 'User', false, false, false, false, Role::ROLE_TYPE_CUSTOM);
		$role2 = $this->createRole(2, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role1, $role2]);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertTrue($result);
	}

	public function testHasGlobalPermissionHandlesException(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willThrowException(new \Exception('Database error'));

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionReturnsTrueWhenUserHasPermission(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canPost';

		// Using role ID 3 (User) instead of 1 (Admin) to test normal permission check
		$role = $this->createRole(3, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 3, true, true, true, false);

		// findByUserId called twice: once in hasAdminRole(), once for permission check
		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 3)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionReturnsFalseWhenUserLacksPermission(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canModerate';

		// Using role ID 2 (Moderator) instead of 1 (Admin) to test normal permission check
		$role = $this->createRole(2, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 2, true, true, true, false);

		// findByUserId called twice: once in hasAdminRole(), once for permission check
		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 2)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionReturnsFalseWhenNoPermissionEntryExists(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canPost';

		$role = $this->createRole(3, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);

		// findByUserId called twice: once in hasAdminRole(), once for permission check
		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 3)
			->willThrowException(new DoesNotExistException('Permission not found'));

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionReturnsTrueForAdminRoleRegardlessOfPermissions(): void {
		$userId = 'admin1';
		$categoryId = 1;
		$permission = 'canModerate';

		// User has Admin role (ID 1)
		$adminRole = $this->createRole(1, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$adminRole]);

		// Should not even check category permissions for Admin
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndRole');

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionReturnsTrueForAdminEvenWithOtherRoles(): void {
		$userId = 'admin1';
		$categoryId = 1;
		$permission = 'canView';

		// User has both Admin (ID 1) and User (ID 3) roles
		$adminRole = $this->createRole(1, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);
		$userRole = $this->createRole(3, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$adminRole, $userRole]);

		// Should not check category permissions for Admin
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndRole');

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasGlobalPermissionForGuestUserUsesGuestRole(): void {
		$userId = null; // Guest user
		$permission = 'canEditRoles';

		$guestRole = $this->createRole(4, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willReturn($guestRole);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasGlobalPermissionForGuestUserReturnsFalseWhenNoGuestRole(): void {
		$userId = null; // Guest user
		$permission = 'canEditRoles';

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willThrowException(new DoesNotExistException('Guest role not found'));

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionForGuestUserUsesGuestRole(): void {
		$userId = null; // Guest user
		$categoryId = 1;
		$permission = 'canView';

		$guestRole = $this->createRole(4, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 4, true, false, false, false);

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willReturn($guestRole);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 4)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionForGuestUserReturnsFalseWhenNoGuestRole(): void {
		$userId = null; // Guest user
		$categoryId = 1;
		$permission = 'canView';

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willThrowException(new DoesNotExistException('Guest role not found'));

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionForGuestUserReturnsFalseWhenNoPermission(): void {
		$userId = null; // Guest user
		$categoryId = 1;
		$permission = 'canPost';

		$guestRole = $this->createRole(4, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 4, true, false, false, false);

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willReturn($guestRole);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 4)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertFalse($result);
	}

	public function testGetCategoryIdFromThreadReturnsCorrectId(): void {
		$threadId = 1;
		$categoryId = 5;

		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setCategoryId($categoryId);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$result = $this->service->getCategoryIdFromThread($threadId);

		$this->assertEquals($categoryId, $result);
	}

	public function testGetCategoryIdFromThreadThrowsWhenThreadNotFound(): void {
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$this->expectException(DoesNotExistException::class);
		$this->service->getCategoryIdFromThread($threadId);
	}

	public function testGetCategoryIdFromPostReturnsCorrectId(): void {
		$postId = 1;
		$threadId = 2;
		$categoryId = 3;

		$post = new Post();
		$post->setId($postId);
		$post->setThreadId($threadId);
		$post->setIsFirstPost(false);

		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setCategoryId($categoryId);

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$result = $this->service->getCategoryIdFromPost($postId);

		$this->assertEquals($categoryId, $result);
	}

	public function testGetCategoryIdFromPostThrowsWhenPostNotFound(): void {
		$postId = 999;

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willThrowException(new DoesNotExistException('Post not found'));

		$this->expectException(DoesNotExistException::class);
		$this->service->getCategoryIdFromPost($postId);
	}

	public function testIsPostAuthorReturnsTrueWhenUserIsAuthor(): void {
		$userId = 'user1';
		$postId = 1;

		$post = new Post();
		$post->setId($postId);
		$post->setAuthorId($userId);
		$post->setIsFirstPost(false);

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$result = $this->service->isPostAuthor($userId, $postId);

		$this->assertTrue($result);
	}

	public function testIsPostAuthorReturnsFalseWhenUserIsNotAuthor(): void {
		$userId = 'user1';
		$postId = 1;

		$post = new Post();
		$post->setId($postId);
		$post->setAuthorId('user2');
		$post->setIsFirstPost(false);

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$result = $this->service->isPostAuthor($userId, $postId);

		$this->assertFalse($result);
	}

	public function testIsPostAuthorReturnsFalseWhenPostNotFound(): void {
		$userId = 'user1';
		$postId = 999;

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willThrowException(new DoesNotExistException('Post not found'));

		$result = $this->service->isPostAuthor($userId, $postId);

		$this->assertFalse($result);
	}

	public function testIsThreadAuthorReturnsTrueWhenUserIsAuthor(): void {
		$userId = 'user1';
		$threadId = 1;

		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setAuthorId($userId);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$result = $this->service->isThreadAuthor($userId, $threadId);

		$this->assertTrue($result);
	}

	public function testIsThreadAuthorReturnsFalseWhenUserIsNotAuthor(): void {
		$userId = 'user1';
		$threadId = 1;

		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setAuthorId('user2');

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$result = $this->service->isThreadAuthor($userId, $threadId);

		$this->assertFalse($result);
	}

	public function testIsThreadAuthorReturnsFalseWhenThreadNotFound(): void {
		$userId = 'user1';
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$result = $this->service->isThreadAuthor($userId, $threadId);

		$this->assertFalse($result);
	}

	public function testGetAccessibleCategoriesReturnsViewableCategory(): void {
		$userId = 'user1';
		$role = $this->createRole(1, 'User', false, false, false, false, Role::ROLE_TYPE_CUSTOM);

		$category1 = $this->createCategory(1, 'Category 1', 'category-1');

		$perm1 = $this->createCategoryPerm(1, 1, 1, true, false, false, false);

		// Set up mocks without strict expectations
		$this->roleMapper->method('findByUserId')
			->willReturn([$role]);

		$this->categoryMapper->method('findAll')
			->willReturn([$category1]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturn($perm1);

		$result = $this->service->getAccessibleCategories($userId);

		$this->assertCount(1, $result);
		$this->assertContains(1, $result);
	}

	public function testGetAccessibleCategoriesForGuestUser(): void {
		$guestRole = $this->createRole(1, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);

		$category1 = $this->createCategory(1, 'Public Category', 'public-category');
		$category2 = $this->createCategory(2, 'Private Category', 'private-category');

		$perm1 = $this->createCategoryPerm(1, 1, 1, true, false, false, false);
		// Category 2 has no guest view permission

		$this->roleMapper->method('findByRoleType')
			->willReturn($guestRole);

		$this->categoryMapper->method('findAll')
			->willReturn([$category1, $category2]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturnCallback(function ($categoryId, $roleId) use ($perm1) {
				if ($categoryId == 1 && $roleId == 1) {
					return $perm1;
				}
				throw new DoesNotExistException('Permission not found');
			});

		$result = $this->service->getAccessibleCategories(null);

		$this->assertCount(1, $result);
		$this->assertContains(1, $result);
		$this->assertNotContains(2, $result);
	}

	public function testGetAccessibleCategoriesForGuestUserWithNoGuestRole(): void {
		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willThrowException(new DoesNotExistException('Guest role not found'));

		$result = $this->service->getAccessibleCategories(null);

		$this->assertCount(0, $result);
	}

	public function testGetAccessibleCategoriesReturnsEmptyWhenUserHasNoRoles(): void {
		$userId = 'user1';

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([]);

		$result = $this->service->getAccessibleCategories($userId);

		$this->assertCount(0, $result);
	}

	public function testGetAccessibleCategoriesReturnsEmptyWhenNoCategoriesExist(): void {
		$userId = 'user1';
		$role = $this->createRole(1, 'User', false, false, false, false, Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);

		$result = $this->service->getAccessibleCategories($userId);

		$this->assertCount(0, $result);
	}

	public function testGetAccessibleCategoriesHandlesExceptions(): void {
		$userId = 'user1';

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willThrowException(new \Exception('Database error'));

		$result = $this->service->getAccessibleCategories($userId);

		$this->assertCount(0, $result);
	}

	private function createUserRole(int $id, string $userId, int $roleId): UserRole {
		$userRole = new UserRole();
		$userRole->setId($id);
		$userRole->setUserId($userId);
		$userRole->setRoleId($roleId);
		$userRole->setCreatedAt(time());
		return $userRole;
	}

	private function createRole(int $id, string $name, bool $canAccessAdminTools, bool $canEditRoles, bool $canEditCategories, bool $isSystemRole = false, string $roleType = Role::ROLE_TYPE_CUSTOM): Role {
		$role = new Role();
		$role->setId($id);
		$role->setName($name);
		$role->setCanAccessAdminTools($canAccessAdminTools);
		$role->setCanEditRoles($canEditRoles);
		$role->setCanEditCategories($canEditCategories);
		$role->setIsSystemRole($isSystemRole);
		$role->setRoleType($roleType);
		$role->setCreatedAt(time());
		return $role;
	}

	private function createCategoryPerm(int $id, int $categoryId, int $roleId, bool $canView, bool $canPost, bool $canReply, bool $canModerate): CategoryPerm {
		$perm = new CategoryPerm();
		$perm->setId($id);
		$perm->setCategoryId($categoryId);
		$perm->setRoleId($roleId);
		$perm->setCanView($canView);
		$perm->setCanPost($canPost);
		$perm->setCanReply($canReply);
		$perm->setCanModerate($canModerate);
		return $perm;
	}

	private function createCategory(int $id, string $name, string $slug): Category {
		$category = new Category();
		$category->setId($id);
		$category->setName($name);
		$category->setSlug($slug);
		$category->setDescription('');
		$category->setSortOrder(0);
		$category->setThreadCount(0);
		$category->setPostCount(0);
		$category->setCreatedAt(time());
		$category->setUpdatedAt(time());
		return $category;
	}
}
