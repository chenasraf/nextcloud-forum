<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

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

		$userRole = $this->createUserRole(1, $userId, 1);
		$role = $this->createRole(1, 'Admin', true, true, true);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole]);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($role);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertTrue($result);
	}

	public function testHasGlobalPermissionReturnsFalseWhenUserLacksPermission(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$userRole = $this->createUserRole(1, $userId, 1);
		$role = $this->createRole(1, 'User', false, false, false);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole]);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($role);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasGlobalPermissionReturnsFalseWhenUserHasNoRoles(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([]);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasGlobalPermissionChecksMultipleRoles(): void {
		$userId = 'user1';
		$permission = 'canEditCategories';

		$userRole1 = $this->createUserRole(1, $userId, 1);
		$userRole2 = $this->createUserRole(2, $userId, 2);

		$role1 = $this->createRole(1, 'User', false, false, false);
		$role2 = $this->createRole(2, 'Moderator', true, false, true);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole1, $userRole2]);

		$this->roleMapper->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[1, $role1],
				[2, $role2],
			]);

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertTrue($result);
	}

	public function testHasGlobalPermissionHandlesNonExistentRole(): void {
		$userId = 'user1';
		$permission = 'canEditRoles';

		$userRole = $this->createUserRole(1, $userId, 999);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole]);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('Role not found'));

		$result = $this->service->hasGlobalPermission($userId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionReturnsTrueWhenUserHasPermission(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canPost';

		$userRole = $this->createUserRole(1, $userId, 1);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 1, true, true, true, false);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 1)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertTrue($result);
	}

	public function testHasCategoryPermissionReturnsFalseWhenUserLacksPermission(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canModerate';

		$userRole = $this->createUserRole(1, $userId, 1);
		$categoryPerm = $this->createCategoryPerm(1, $categoryId, 1, true, true, true, false);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 1)
			->willReturn($categoryPerm);

		$result = $this->service->hasCategoryPermission($userId, $categoryId, $permission);

		$this->assertFalse($result);
	}

	public function testHasCategoryPermissionReturnsFalseWhenNoPermissionEntryExists(): void {
		$userId = 'user1';
		$categoryId = 1;
		$permission = 'canPost';

		$userRole = $this->createUserRole(1, $userId, 1);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole]);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRole')
			->with($categoryId, 1)
			->willThrowException(new DoesNotExistException('Permission not found'));

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

	private function createUserRole(int $id, string $userId, int $roleId): UserRole {
		$userRole = new UserRole();
		$userRole->setId($id);
		$userRole->setUserId($userId);
		$userRole->setRoleId($roleId);
		$userRole->setCreatedAt(time());
		return $userRole;
	}

	private function createRole(int $id, string $name, bool $canAccessAdminTools, bool $canEditRoles, bool $canEditCategories): Role {
		$role = new Role();
		$role->setId($id);
		$role->setName($name);
		$role->setCanAccessAdminTools($canAccessAdminTools);
		$role->setCanEditRoles($canEditRoles);
		$role->setCanEditCategories($canEditCategories);
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
}
