<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\UserRoleController;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\UserRole;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Service\UserRoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserRoleControllerTest extends TestCase {
	private UserRoleController $controller;
	private UserRoleMapper $userRoleMapper;
	private RoleMapper $roleMapper;
	private UserRoleService $userRoleService;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userRoleMapper = $this->createMock(UserRoleMapper::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->userRoleService = $this->createMock(UserRoleService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new UserRoleController(
			Application::APP_ID,
			$this->request,
			$this->userRoleMapper,
			$this->roleMapper,
			$this->userRoleService,
			$this->logger
		);
	}

	public function testByUserReturnsRolesWithUserRoleId(): void {
		$userId = 'testuser';

		// Create UserRole entities
		$userRole1 = $this->createUserRole(101, $userId, 1);
		$userRole2 = $this->createUserRole(102, $userId, 2);

		// Create corresponding Role entities
		$adminRole = $this->createRole(1, 'Admin', Role::ROLE_TYPE_ADMIN);
		$moderatorRole = $this->createRole(2, 'Moderator', Role::ROLE_TYPE_MODERATOR);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole1, $userRole2]);

		$this->roleMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(function ($roleId) use ($adminRole, $moderatorRole) {
				return $roleId === 1 ? $adminRole : $moderatorRole;
			});

		$response = $this->controller->byUser($userId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertIsArray($data);
		$this->assertCount(2, $data);

		// Verify first role has userRoleId and role data
		$this->assertEquals(1, $data[0]['id']);
		$this->assertEquals('Admin', $data[0]['name']);
		$this->assertEquals(Role::ROLE_TYPE_ADMIN, $data[0]['roleType']);
		$this->assertEquals(101, $data[0]['userRoleId']);

		// Verify second role has userRoleId and role data
		$this->assertEquals(2, $data[1]['id']);
		$this->assertEquals('Moderator', $data[1]['name']);
		$this->assertEquals(Role::ROLE_TYPE_MODERATOR, $data[1]['roleType']);
		$this->assertEquals(102, $data[1]['userRoleId']);
	}

	public function testByUserReturnsEmptyArrayWhenNoRoles(): void {
		$userId = 'testuser';

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([]);

		$response = $this->controller->byUser($userId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertIsArray($data);
		$this->assertCount(0, $data);
	}

	public function testByUserSkipsDeletedRoles(): void {
		$userId = 'testuser';

		// Create UserRole entities - one with existing role, one with deleted role
		$userRole1 = $this->createUserRole(101, $userId, 1);
		$userRole2 = $this->createUserRole(102, $userId, 999); // Deleted role

		$adminRole = $this->createRole(1, 'Admin', Role::ROLE_TYPE_ADMIN);

		$this->userRoleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$userRole1, $userRole2]);

		$this->roleMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(function ($roleId) use ($adminRole) {
				if ($roleId === 1) {
					return $adminRole;
				}
				throw new DoesNotExistException('Role not found');
			});

		$response = $this->controller->byUser($userId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		// Only the existing role should be returned
		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertEquals(1, $data[0]['id']);
		$this->assertEquals(101, $data[0]['userRoleId']);
	}

	public function testDestroyRemovesUserRole(): void {
		$userRoleId = 101;
		$userRole = $this->createUserRole($userRoleId, 'testuser', 2);
		$moderatorRole = $this->createRole(2, 'Moderator', Role::ROLE_TYPE_MODERATOR);

		$this->userRoleMapper->expects($this->once())
			->method('find')
			->with($userRoleId)
			->willReturn($userRole);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with(2)
			->willReturn($moderatorRole);

		$this->userRoleMapper->expects($this->once())
			->method('delete')
			->with($userRole);

		$response = $this->controller->destroy($userRoleId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testDestroyReturnsNotFoundForNonExistentUserRole(): void {
		$userRoleId = 999;

		$this->userRoleMapper->expects($this->once())
			->method('find')
			->with($userRoleId)
			->willThrowException(new DoesNotExistException('User role not found'));

		$this->userRoleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($userRoleId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'User role not found'], $data);
	}

	public function testDestroyReturnsForbiddenForGuestRole(): void {
		$userRoleId = 101;
		$userRole = $this->createUserRole($userRoleId, 'testuser', 4);
		$guestRole = $this->createRole(4, 'Guest', Role::ROLE_TYPE_GUEST);

		$this->userRoleMapper->expects($this->once())
			->method('find')
			->with($userRoleId)
			->willReturn($userRole);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with(4)
			->willReturn($guestRole);

		$this->userRoleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($userRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Guest role cannot be removed from users'], $data);
	}

	public function testCreateAssignsRoleToUser(): void {
		$userId = 'testuser';
		$roleId = 2;

		$moderatorRole = $this->createRole($roleId, 'Moderator', Role::ROLE_TYPE_MODERATOR);
		$userRole = $this->createUserRole(101, $userId, $roleId);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willReturn($moderatorRole);

		$this->userRoleService->expects($this->once())
			->method('hasRole')
			->with($userId, $roleId)
			->willReturn(false);

		$this->userRoleService->expects($this->once())
			->method('assignRole')
			->with($userId, $roleId, false)
			->willReturn($userRole);

		$response = $this->controller->create($userId, $roleId);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(101, $data['id']);
		$this->assertEquals($userId, $data['userId']);
		$this->assertEquals($roleId, $data['roleId']);
	}

	public function testCreateReturnsForbiddenForGuestRole(): void {
		$userId = 'testuser';
		$roleId = 4;

		$guestRole = $this->createRole($roleId, 'Guest', Role::ROLE_TYPE_GUEST);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willReturn($guestRole);

		$this->userRoleService->expects($this->never())
			->method('assignRole');

		$response = $this->controller->create($userId, $roleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Guest role cannot be assigned to users'], $data);
	}

	public function testCreateReturnsConflictWhenUserAlreadyHasRole(): void {
		$userId = 'testuser';
		$roleId = 2;

		$moderatorRole = $this->createRole($roleId, 'Moderator', Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willReturn($moderatorRole);

		$this->userRoleService->expects($this->once())
			->method('hasRole')
			->with($userId, $roleId)
			->willReturn(true);

		$this->userRoleService->expects($this->never())
			->method('assignRole');

		$response = $this->controller->create($userId, $roleId);

		$this->assertEquals(Http::STATUS_CONFLICT, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'User already has this role'], $data);
	}

	public function testByRoleReturnsUsersWithRole(): void {
		$roleId = 2;

		$userRole1 = $this->createUserRole(101, 'user1', $roleId);
		$userRole2 = $this->createUserRole(102, 'user2', $roleId);

		$this->userRoleMapper->expects($this->once())
			->method('findByRoleId')
			->with($roleId)
			->willReturn([$userRole1, $userRole2]);

		$response = $this->controller->byRole($roleId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals('user1', $data[0]['userId']);
		$this->assertEquals('user2', $data[1]['userId']);
	}

	private function createUserRole(int $id, string $userId, int $roleId): UserRole {
		$userRole = new UserRole();
		$userRole->setId($id);
		$userRole->setUserId($userId);
		$userRole->setRoleId($roleId);
		$userRole->setCreatedAt(time());
		return $userRole;
	}

	private function createRole(int $id, string $name, string $roleType): Role {
		$role = new Role();
		$role->setId($id);
		$role->setName($name);
		$role->setRoleType($roleType);
		$role->setIsSystemRole($roleType !== Role::ROLE_TYPE_CUSTOM);
		$role->setCanAccessAdminTools($roleType === Role::ROLE_TYPE_ADMIN);
		$role->setCanEditRoles($roleType === Role::ROLE_TYPE_ADMIN);
		$role->setCanEditCategories($roleType === Role::ROLE_TYPE_ADMIN);
		$role->setCreatedAt(time());
		return $role;
	}
}
