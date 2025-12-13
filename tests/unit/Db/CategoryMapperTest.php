<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryMapperTest extends TestCase {
	private CategoryMapper $mapper;
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->mapper = new CategoryMapper(
			$this->db,
			$this->userSession,
			$this->roleMapper,
			$this->logger
		);
	}

	public function testIsCurrentUserAdminReturnsTrueForAdminRole(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin1');

		$this->userSession->method('getUser')->willReturn($user);

		$adminRole = new Role();
		$adminRole->setId(1);
		$adminRole->setRoleType(Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('admin1')
			->willReturn([$adminRole]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertTrue($result);
	}

	public function testIsCurrentUserAdminReturnsFalseForNonAdminRole(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$userRole = new Role();
		$userRole->setId(3);
		$userRole->setRoleType(Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([$userRole]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertFalse($result);
	}

	public function testIsCurrentUserAdminReturnsFalseWhenNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertFalse($result);
	}

	public function testGetUserRoleIdsReturnsGuestRoleForUnauthenticatedUser(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$guestRole = new Role();
		$guestRole->setId(4);
		$guestRole->setRoleType(Role::ROLE_TYPE_GUEST);

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with('guest')
			->willReturn($guestRole);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('getUserRoleIds');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertEquals([4], $result);
	}

	public function testGetUserRoleIdsReturnsEmptyArrayWhenGuestRoleNotFound(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->roleMapper->expects($this->once())
			->method('findByRoleType')
			->with('guest')
			->willThrowException(new DoesNotExistException('Guest role not found'));

		// Expect logger to be called when guest role is not found
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Guest role not found'));

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('getUserRoleIds');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertEquals([], $result);
	}

	public function testGetUserRoleIdsReturnsUserRolesForAuthenticatedUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$role1 = new Role();
		$role1->setId(3);
		$role1->setRoleType(Role::ROLE_TYPE_DEFAULT);

		$role2 = new Role();
		$role2->setId(5);
		$role2->setRoleType(Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([$role1, $role2]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('getUserRoleIds');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertEquals([3, 5], $result);
	}

	public function testFilterByPermissionsSkipsFilteringForAdminUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin1');

		$this->userSession->method('getUser')->willReturn($user);

		$adminRole = new Role();
		$adminRole->setId(1);
		$adminRole->setRoleType(Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('admin1')
			->willReturn([$adminRole]);

		$category1 = new Category();
		$category1->setId(1);

		$category2 = new Category();
		$category2->setId(2);

		$categories = [$category1, $category2];

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('filterByPermissions');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper, $categories);

		// Admin should see all categories
		$this->assertEquals($categories, $result);
	}

	public function testFilterByPermissionsReturnsEmptyArrayWhenNoCategories(): void {
		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('filterByPermissions');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper, []);

		$this->assertEquals([], $result);
	}

	public function testIsCurrentUserAdminReturnsTrueForModeratorRole(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('mod1');

		$this->userSession->method('getUser')->willReturn($user);

		$modRole = new Role();
		$modRole->setId(2);
		$modRole->setRoleType(Role::ROLE_TYPE_MODERATOR);

		$adminRole = new Role();
		$adminRole->setId(1);
		$adminRole->setRoleType(Role::ROLE_TYPE_ADMIN);

		// User has both moderator and admin roles
		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('mod1')
			->willReturn([$modRole, $adminRole]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		// Should return true because one of the roles is admin
		$this->assertTrue($result);
	}

	public function testGetUserRoleIdsReturnsMultipleRoles(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$role1 = new Role();
		$role1->setId(3);
		$role1->setRoleType(Role::ROLE_TYPE_DEFAULT);

		$role2 = new Role();
		$role2->setId(5);
		$role2->setRoleType(Role::ROLE_TYPE_CUSTOM);

		$role3 = new Role();
		$role3->setId(7);
		$role3->setRoleType(Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([$role1, $role2, $role3]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('getUserRoleIds');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertEquals([3, 5, 7], $result);
	}

	public function testIsCurrentUserAdminReturnsFalseWhenUserHasNoRoles(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertFalse($result);
	}

	public function testGetUserRoleIdsReturnsEmptyArrayWhenUserHasNoRoles(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('getUserRoleIds');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertEquals([], $result);
	}

	public function testIsCurrentUserAdminWithModeratorRoleOnly(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('mod1');

		$this->userSession->method('getUser')->willReturn($user);

		$modRole = new Role();
		$modRole->setId(2);
		$modRole->setRoleType(Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('mod1')
			->willReturn([$modRole]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		// Moderator is not admin
		$this->assertFalse($result);
	}

	public function testIsCurrentUserAdminWithDefaultRoleType(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$defaultRole = new Role();
		$defaultRole->setId(3);
		$defaultRole->setRoleType(Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([$defaultRole]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertFalse($result);
	}

	public function testIsCurrentUserAdminWithCustomRoleType(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');

		$this->userSession->method('getUser')->willReturn($user);

		$customRole = new Role();
		$customRole->setId(10);
		$customRole->setRoleType(Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with('user1')
			->willReturn([$customRole]);

		// Use reflection to call private method
		$reflection = new \ReflectionClass($this->mapper);
		$method = $reflection->getMethod('isCurrentUserAdmin');
		$method->setAccessible(true);

		$result = $method->invoke($this->mapper);

		$this->assertFalse($result);
	}
}
