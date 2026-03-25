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
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PermissionServiceTest extends TestCase {
	private PermissionService $service;
	/** @var UserRoleMapper&MockObject */
	private UserRoleMapper $userRoleMapper;
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var CategoryPermMapper&MockObject */
	private CategoryPermMapper $categoryPermMapper;
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $categoryMapper;
	/** @var ThreadMapper&MockObject */
	private ThreadMapper $threadMapper;
	/** @var PostMapper&MockObject */
	private PostMapper $postMapper;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->userRoleMapper = $this->createMock(UserRoleMapper::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->categoryPermMapper = $this->createMock(CategoryPermMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new PermissionService(
			$this->userRoleMapper,
			$this->roleMapper,
			$this->categoryPermMapper,
			$this->categoryMapper,
			$this->threadMapper,
			$this->postMapper,
			$this->userManager,
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

	public function testHasGlobalPermissionCanManageUsers(): void {
		$userId = 'user1';
		$role = $this->createRole(1, 'Manager', false, false, false, false, Role::ROLE_TYPE_CUSTOM, true, false);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->assertTrue($this->service->hasGlobalPermission($userId, 'canManageUsers'));
		$this->assertFalse($role->getCanEditBbcodes());
	}

	public function testHasGlobalPermissionCanEditBbcodes(): void {
		$userId = 'user1';
		$role = $this->createRole(1, 'BBCodeEditor', false, false, false, false, Role::ROLE_TYPE_CUSTOM, false, true);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->assertTrue($this->service->hasGlobalPermission($userId, 'canEditBbcodes'));
		$this->assertFalse($role->getCanManageUsers());
	}

	public function testHasGlobalPermissionReturnsFalseForNewPermissionsWhenNotSet(): void {
		$userId = 'user1';
		$role = $this->createRole(1, 'Basic', false, false, false, false, Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->assertFalse($this->service->hasGlobalPermission($userId, 'canManageUsers'));
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

	public function testHasCategoryPermissionCanModerateReturnsTrueForModeratorWithPermission(): void {
		$userId = 'mod1';
		$categoryId = 1;
		$permission = 'canModerate';

		$role = $this->createRole(2, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 2, true, true, true, true);

		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 2)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionCanModerateReturnsTrueForCustomRoleWithPermission(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canModerate';

		$role = $this->createRole(10, 'Category Moderator', false, false, false, false, Role::ROLE_TYPE_CUSTOM);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 10, true, true, true, true);

		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 10)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionCanModerateReturnsFalseForCustomRoleWithoutPermission(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canModerate';

		$role = $this->createRole(10, 'Limited Role', false, false, false, false, Role::ROLE_TYPE_CUSTOM);
		// Has view/post/reply but NOT moderate
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 10, true, true, true, false);

		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 10)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionCanModerateChecksAllRoles(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canModerate';

		// Default role has no moderate permission, custom role does
		$defaultRole = $this->createRole(3, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);
		$customRole = $this->createRole(10, 'Category Mod', false, false, false, false, Role::ROLE_TYPE_CUSTOM);
		$defaultPerm = $this->createCategoryPerm(1, $categoryId, 3, true, true, true, false);
		$customPerm = $this->createCategoryPerm(2, $categoryId, 10, true, true, true, true);

		$this->roleMapper->expects($this->exactly(2))
			->method('findByUserId')
			->with($userId)
			->willReturn([$defaultRole, $customRole]);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('findByCategoryAndRole')
			->willReturnCallback(function ($catId, $roleId) use ($categoryId, $defaultPerm, $customPerm) {
				$this->assertEquals($categoryId, $catId);
				if ($roleId === 3) {
					return $defaultPerm;
				}
				if ($roleId === 10) {
					return $customPerm;
				}
				throw new DoesNotExistException('Not found');
			});

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionCanModerateIsCategorySpecific(): void {
		$userId = 'user1';
		$permission = 'canModerate';

		// User can moderate category 1 but not category 2
		$role = $this->createRole(10, 'Partial Mod', false, false, false, false, Role::ROLE_TYPE_CUSTOM);
		$perm1 = $this->createCategoryPerm(1, 1, 10, true, true, true, true);  // canModerate=true
		$perm2 = $this->createCategoryPerm(2, 2, 10, true, true, true, false); // canModerate=false

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturnCallback(function ($catId, $roleId) use ($perm1, $perm2) {
				if ($catId === 1 && $roleId === 10) {
					return $perm1;
				}
				if ($catId === 2 && $roleId === 10) {
					return $perm2;
				}
				throw new DoesNotExistException('Not found');
			});

		$this->assertTrue($this->service->hasCategoryPermission($userId, 1, $permission));
		$this->assertFalse($this->service->hasCategoryPermission($userId, 2, $permission));
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
		$category1 = $this->createCategory(1, 'Category 1', 'category-1');

		$this->categoryMapper->method('findAll')
			->willReturn([$category1]);

		$this->roleMapper->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willThrowException(new DoesNotExistException('Guest role not found'));

		$result = $this->service->getAccessibleCategories(null);

		$this->assertCount(0, $result);
	}

	public function testGetAccessibleCategoriesReturnsEmptyWhenUserHasNoRoles(): void {
		$userId = 'user1';
		$category1 = $this->createCategory(1, 'Category 1', 'category-1');

		$this->categoryMapper->method('findAll')
			->willReturn([$category1]);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		$result = $this->service->getAccessibleCategories($userId);

		$this->assertCount(0, $result);
	}

	public function testGetAccessibleCategoriesReturnsEmptyWhenNoCategoriesExist(): void {
		$userId = 'user1';

		$this->categoryMapper->expects($this->once())
			->method('findAll')
			->willReturn([]);

		$result = $this->service->getAccessibleCategories($userId);

		$this->assertCount(0, $result);
	}

	public function testGetAccessibleCategoriesHandlesExceptions(): void {
		$userId = 'user1';

		$this->categoryMapper->expects($this->once())
			->method('findAll')
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

	private function createRole(int $id, string $name, bool $canAccessAdminTools, bool $canEditRoles, bool $canEditCategories, bool $isSystemRole = false, string $roleType = Role::ROLE_TYPE_CUSTOM, bool $canManageUsers = false, bool $canEditBbcodes = false, bool $canAccessModeration = false): Role {
		$role = new Role();
		$role->setId($id);
		$role->setName($name);
		$role->setCanAccessAdminTools($canAccessAdminTools);
		$role->setCanManageUsers($canManageUsers);
		$role->setCanEditRoles($canEditRoles);
		$role->setCanEditCategories($canEditCategories);
		$role->setCanEditBbcodes($canEditBbcodes);
		$role->setCanAccessModeration($canAccessModeration);
		$role->setIsSystemRole($isSystemRole);
		$role->setRoleType($roleType);
		$role->setCreatedAt(time());
		return $role;
	}

	// ---- Team (circle) permission tests ----

	/**
	 * Create a PermissionService partial mock where getUserCircleIds is overridden
	 * to return the given circle IDs, allowing team permission paths to be tested
	 * without the Circles app installed.
	 *
	 * @param array<string>|null $circleIds Circle IDs to return, or null for "Circles unavailable"
	 */
	private function createServiceWithCircleIds(?array $circleIds): PermissionService {
		$service = $this->getMockBuilder(PermissionService::class)
			->setConstructorArgs([
				$this->userRoleMapper,
				$this->roleMapper,
				$this->categoryPermMapper,
				$this->categoryMapper,
				$this->threadMapper,
				$this->postMapper,
				$this->userManager,
				$this->logger,
			])
			->onlyMethods(['getUserCircleIds'])
			->getMock();

		$service->method('getUserCircleIds')
			->willReturn($circleIds);

		return $service;
	}

	public function testHasCategoryPermissionGrantedByTeamWhenRoleDenies(): void {
		$userId = 'user1';
		$categoryId = 3;

		// User role denies view on this category
		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);
		$rolePerm = $this->createCategoryPerm(1, $categoryId, 3, false, false, false, false);

		// But team grants view
		$teamPerm = $this->createTeamCategoryPerm(10, $categoryId, 'circle-abc', true, false, false, false);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturn($rolePerm);

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->with($categoryId, ['circle-abc'])
			->willReturn([$teamPerm]);

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$this->assertTrue($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testHasCategoryPermissionGrantedByTeamWhenNoRolePermEntryExists(): void {
		$userId = 'user1';
		$categoryId = 3;

		// User role has no permission entry for this category at all
		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);

		$teamPerm = $this->createTeamCategoryPerm(10, $categoryId, 'circle-abc', true, true, true, false);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->with($categoryId, ['circle-abc'])
			->willReturn([$teamPerm]);

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$this->assertTrue($service->hasCategoryPermission($userId, $categoryId, 'canPost'));
	}

	public function testHasCategoryPermissionDeniedByBothRoleAndTeam(): void {
		$userId = 'user1';
		$categoryId = 3;

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);
		$rolePerm = $this->createCategoryPerm(1, $categoryId, 3, false, false, false, false);

		// Team also denies
		$teamPerm = $this->createTeamCategoryPerm(10, $categoryId, 'circle-abc', false, false, false, false);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturn($rolePerm);

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->with($categoryId, ['circle-abc'])
			->willReturn([$teamPerm]);

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$this->assertFalse($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testHasCategoryPermissionTeamNotCheckedForGuestUser(): void {
		$categoryId = 1;

		$guestRole = $this->createRole(4, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);
		$rolePerm = $this->createCategoryPerm(1, $categoryId, 4, false, false, false, false);

		$this->roleMapper->method('findByRoleType')
			->with(Role::ROLE_TYPE_GUEST)
			->willReturn($guestRole);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturn($rolePerm);

		// Team mapper should never be called for guest users
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndTeamIds');

		$result = $this->service->hasCategoryPermission(null, $categoryId, 'canView');

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionTeamNotCheckedWhenCirclesUnavailable(): void {
		$userId = 'user1';
		$categoryId = 3;

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		// Circles unavailable → null circle IDs → no team check
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndTeamIds');

		$service = $this->createServiceWithCircleIds(null);

		$this->assertFalse($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testHasCategoryPermissionTeamNotCheckedWhenUserHasNoCircles(): void {
		$userId = 'user1';
		$categoryId = 3;

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		// User is in no circles
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndTeamIds');

		$service = $this->createServiceWithCircleIds([]);

		$this->assertFalse($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testHasCategoryPermissionMultipleTeamsOneGrants(): void {
		$userId = 'user1';
		$categoryId = 3;

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		// User is in two teams; first denies, second grants
		$teamPerm1 = $this->createTeamCategoryPerm(10, $categoryId, 'circle-aaa', false, false, false, false);
		$teamPerm2 = $this->createTeamCategoryPerm(11, $categoryId, 'circle-bbb', true, true, false, false);

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->with($categoryId, ['circle-aaa', 'circle-bbb'])
			->willReturn([$teamPerm1, $teamPerm2]);

		$service = $this->createServiceWithCircleIds(['circle-aaa', 'circle-bbb']);

		$this->assertTrue($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testHasCategoryPermissionTeamGrantsSpecificPermissionOnly(): void {
		$userId = 'user1';
		$categoryId = 3;

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		// Team grants view but not post
		$teamPerm = $this->createTeamCategoryPerm(10, $categoryId, 'circle-abc', true, false, false, false);

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->willReturn([$teamPerm]);

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$this->assertTrue($service->hasCategoryPermission($userId, $categoryId, 'canView'));
		$this->assertFalse($service->hasCategoryPermission($userId, $categoryId, 'canPost'));
	}

	public function testHasCategoryPermissionAdminBypassesTeamCheck(): void {
		$userId = 'admin1';
		$categoryId = 3;

		$adminRole = $this->createRole(1, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$adminRole]);

		// Neither role perms nor team perms should be checked
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndRole');
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndTeamIds');

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$this->assertTrue($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testHasCategoryPermissionRoleGrantsBeforeTeamCheck(): void {
		$userId = 'user1';
		$categoryId = 1;

		// Role already grants the permission
		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);
		$rolePerm = $this->createCategoryPerm(1, $categoryId, 3, true, true, true, false);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturn($rolePerm);

		// Team mapper should not be called since role already granted
		$this->categoryPermMapper->expects($this->never())
			->method('findByCategoryAndTeamIds');

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$this->assertTrue($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	public function testGetAccessibleCategoriesIncludesTeamOnlyCategory(): void {
		$userId = 'user1';

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);
		$category1 = $this->createCategory(1, 'Role Access', 'role-access');
		$category2 = $this->createCategory(2, 'Team Only', 'team-only');
		$category3 = $this->createCategory(3, 'No Access', 'no-access');

		$rolePerm1 = $this->createCategoryPerm(1, 1, 3, true, true, true, false);
		$teamPerm2 = $this->createTeamCategoryPerm(10, 2, 'circle-abc', true, false, false, false);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryMapper->method('findAll')
			->willReturn([$category1, $category2, $category3]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturnCallback(function ($catId, $roleId) use ($rolePerm1) {
				if ($catId === 1 && $roleId === 3) {
					return $rolePerm1;
				}
				throw new DoesNotExistException('Not found');
			});

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->willReturnCallback(function ($catId, $teamIds) use ($teamPerm2) {
				if ($catId === 2) {
					return [$teamPerm2];
				}
				return [];
			});

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		$result = $service->getAccessibleCategories($userId);

		$this->assertCount(2, $result);
		$this->assertContains(1, $result);
		$this->assertContains(2, $result);
		$this->assertNotContains(3, $result);
	}

	public function testGetAccessibleCategoriesNoTeamAccessWhenCirclesUnavailable(): void {
		$userId = 'user1';

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);
		$category1 = $this->createCategory(1, 'Role Access', 'role-access');
		$category2 = $this->createCategory(2, 'Team Only', 'team-only');

		$rolePerm1 = $this->createCategoryPerm(1, 1, 3, true, true, true, false);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryMapper->method('findAll')
			->willReturn([$category1, $category2]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willReturnCallback(function ($catId, $roleId) use ($rolePerm1) {
				if ($catId === 1 && $roleId === 3) {
					return $rolePerm1;
				}
				throw new DoesNotExistException('Not found');
			});

		// Circles unavailable
		$service = $this->createServiceWithCircleIds(null);

		$result = $service->getAccessibleCategories($userId);

		// Only role-granted category, not the team-only one
		$this->assertCount(1, $result);
		$this->assertContains(1, $result);
	}

	public function testHasCategoryPermissionTeamMapperExceptionHandledGracefully(): void {
		$userId = 'user1';
		$categoryId = 3;

		$role = $this->createRole(3, 'User', false, false, false, false, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		$this->categoryPermMapper->method('findByCategoryAndRole')
			->willThrowException(new DoesNotExistException('Not found'));

		$this->categoryPermMapper->method('findByCategoryAndTeamIds')
			->willThrowException(new \Exception('Database error'));

		$service = $this->createServiceWithCircleIds(['circle-abc']);

		// Should return false, not throw
		$this->assertFalse($service->hasCategoryPermission($userId, $categoryId, 'canView'));
	}

	// ---- Helper methods ----

	private function createCategoryPerm(int $id, int $categoryId, int $roleId, bool $canView, bool $canPost, bool $canReply, bool $canModerate): CategoryPerm {
		$perm = new CategoryPerm();
		$perm->setId($id);
		$perm->setCategoryId($categoryId);
		$perm->setTargetType('role');
		$perm->setTargetId((string)$roleId);
		$perm->setCanView($canView);
		$perm->setCanPost($canPost);
		$perm->setCanReply($canReply);
		$perm->setCanModerate($canModerate);
		return $perm;
	}

	private function createTeamCategoryPerm(int $id, int $categoryId, string $teamId, bool $canView, bool $canPost, bool $canReply, bool $canModerate): CategoryPerm {
		$perm = new CategoryPerm();
		$perm->setId($id);
		$perm->setCategoryId($categoryId);
		$perm->setTargetType(CategoryPerm::TARGET_TYPE_TEAM);
		$perm->setTargetId($teamId);
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
