<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\RoleController;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RoleControllerTest extends TestCase {
	private RoleController $controller;
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var CategoryPermMapper&MockObject */
	private CategoryPermMapper $categoryPermMapper;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->categoryPermMapper = $this->createMock(CategoryPermMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new RoleController(
			Application::APP_ID,
			$this->request,
			$this->roleMapper,
			$this->categoryPermMapper,
			$this->logger
		);
	}

	public function testUpdateAdminRoleEnforcesAllPermissions(): void {
		$adminRoleId = 1;
		$adminRole = $this->createRole($adminRoleId, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($adminRoleId)
			->willReturn($adminRole);

		$this->roleMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($role) use ($adminRoleId) {
				// Verify Admin role always has all permissions
				$this->assertEquals($adminRoleId, $role->getId());
				$this->assertTrue($role->getCanAccessAdminTools());
				$this->assertTrue($role->getCanEditRoles());
				$this->assertTrue($role->getCanEditCategories());
				return $role;
			});

		// Try to update Admin role with permissions set to false - should be forced to true
		$response = $this->controller->update(
			$adminRoleId,
			'Admin',
			'Administrator role',
			'#ff0000',
			'#ff0000',
			false,  // Try to disable - should be forced to true
			false,  // Try to disable - should be forced to true
			false   // Try to disable - should be forced to true
		);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['canAccessAdminTools']);
		$this->assertTrue($data['canEditRoles']);
		$this->assertTrue($data['canEditCategories']);
	}

	public function testUpdateNonAdminRoleAllowsPermissionChanges(): void {
		$roleId = 2;
		$role = $this->createRole($roleId, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willReturn($role);

		$this->roleMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($role) use ($roleId) {
				// Verify non-admin role can have permissions changed
				$this->assertEquals($roleId, $role->getId());
				$this->assertFalse($role->getCanAccessAdminTools());
				$this->assertTrue($role->getCanEditRoles());
				$this->assertFalse($role->getCanEditCategories());
				return $role;
			});

		$response = $this->controller->update(
			$roleId,
			'Moderator',
			'Moderator role',
			null,
			null,
			false,  // Changed from true
			true,   // Kept true
			false   // Changed from true
		);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteAdminRoleReturnsForbidden(): void {
		$adminRoleId = 1;
		$adminRole = $this->createRole($adminRoleId, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($adminRoleId)
			->willReturn($adminRole);

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($adminRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'System roles cannot be deleted'], $data);
	}

	public function testDeleteModeratorRoleReturnsForbidden(): void {
		$moderatorRoleId = 2;
		$moderatorRole = $this->createRole($moderatorRoleId, 'Moderator', true, false, false, true, Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($moderatorRoleId)
			->willReturn($moderatorRole);

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($moderatorRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'System roles cannot be deleted'], $data);
	}

	public function testDeleteUserRoleReturnsForbidden(): void {
		$userRoleId = 3;
		$userRole = $this->createRole($userRoleId, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($userRoleId)
			->willReturn($userRole);

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($userRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'System roles cannot be deleted'], $data);
	}

	public function testDeleteCustomRoleSuccessfully(): void {
		$customRoleId = 4;
		$customRole = $this->createRole($customRoleId, 'Custom Role', false, false, false, false, Role::ROLE_TYPE_CUSTOM);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($customRoleId)
			->willReturn($customRole);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByRoleId')
			->with($customRoleId);

		$this->roleMapper->expects($this->once())
			->method('delete')
			->with($customRole);

		$response = $this->controller->destroy($customRoleId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testDeleteNonExistentRoleReturnsNotFound(): void {
		$roleId = 999;

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willThrowException(new DoesNotExistException('Role not found'));

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($roleId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Role not found'], $data);
	}

	public function testIndexReturnsAllRoles(): void {
		$role1 = $this->createRole(1, 'Admin', true, true, true, true, Role::ROLE_TYPE_ADMIN);
		$role2 = $this->createRole(2, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);
		$role3 = $this->createRole(3, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->expects($this->once())
			->method('findAll')
			->willReturn([$role1, $role2, $role3]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(3, $data);
		$this->assertEquals('Admin', $data[0]['name']);
		$this->assertEquals('Moderator', $data[1]['name']);
		$this->assertEquals('User', $data[2]['name']);
	}

	public function testShowReturnsRoleSuccessfully(): void {
		$roleId = 2;
		$role = $this->createRole($roleId, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willReturn($role);

		$response = $this->controller->show($roleId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($roleId, $data['id']);
		$this->assertEquals('Moderator', $data['name']);
		$this->assertTrue($data['canAccessAdminTools']);
		$this->assertFalse($data['canEditRoles']);
		$this->assertTrue($data['canEditCategories']);
	}

	public function testShowReturnsNotFoundWhenRoleDoesNotExist(): void {
		$roleId = 999;

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willThrowException(new DoesNotExistException('Role not found'));

		$response = $this->controller->show($roleId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Role not found'], $data);
	}

	public function testCreateRoleSuccessfully(): void {
		$name = 'Custom Role';
		$description = 'A custom role for special users';
		$colorLight = '#ff5722';
		$colorDark = '#d84315';

		$this->roleMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($role) use ($name, $description, $colorLight, $colorDark) {
				$this->assertEquals($name, $role->getName());
				$this->assertEquals($description, $role->getDescription());
				$this->assertEquals($colorLight, $role->getColorLight());
				$this->assertEquals($colorDark, $role->getColorDark());
				$this->assertTrue($role->getCanAccessAdminTools());
				$this->assertFalse($role->getCanEditRoles());
				$this->assertTrue($role->getCanEditCategories());

				// Simulate DB setting ID
				$role->setId(4);
				return $role;
			});

		$response = $this->controller->create(
			$name,
			$description,
			$colorLight,
			$colorDark,
			true,   // canAccessAdminTools
			false,  // canEditRoles
			true    // canEditCategories
		);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(4, $data['id']);
		$this->assertEquals($name, $data['name']);
		$this->assertEquals($description, $data['description']);
	}

	public function testUpdateRoleReturnsNotFoundWhenRoleDoesNotExist(): void {
		$roleId = 999;

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willThrowException(new DoesNotExistException('Role not found'));

		$this->roleMapper->expects($this->never())
			->method('update');

		$response = $this->controller->update($roleId, 'New Name');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Role not found'], $data);
	}

	public function testGetPermissionsReturnsPermissionsForRole(): void {
		$roleId = 2;

		$perm1 = new \OCA\Forum\Db\CategoryPerm();
		$perm1->setId(1);
		$perm1->setCategoryId(1);
		$perm1->setRoleId($roleId);
		$perm1->setCanView(true);
		$perm1->setCanPost(true);
		$perm1->setCanReply(true);
		$perm1->setCanModerate(false);

		$perm2 = new \OCA\Forum\Db\CategoryPerm();
		$perm2->setId(2);
		$perm2->setCategoryId(2);
		$perm2->setRoleId($roleId);
		$perm2->setCanView(true);
		$perm2->setCanPost(false);
		$perm2->setCanReply(false);
		$perm2->setCanModerate(true);

		$this->categoryPermMapper->expects($this->once())
			->method('findByRoleId')
			->with($roleId)
			->willReturn([$perm1, $perm2]);

		$response = $this->controller->getPermissions($roleId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals(1, $data[0]['categoryId']);
		$this->assertTrue($data[0]['canView']);
		$this->assertFalse($data[0]['canModerate']);
		$this->assertEquals(2, $data[1]['categoryId']);
		$this->assertTrue($data[1]['canModerate']);
	}

	public function testUpdatePermissionsSuccessfully(): void {
		$roleId = 2;
		$permissions = [
			['categoryId' => 1, 'canView' => true, 'canModerate' => false],
			['categoryId' => 2, 'canView' => true, 'canModerate' => true],
		];

		$role = $this->createRole($roleId, 'Moderator', true, false, true, true, Role::ROLE_TYPE_MODERATOR);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willReturn($role);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByRoleId')
			->with($roleId);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) use ($roleId) {
				$this->assertEquals($roleId, $perm->getRoleId());
				// Verify canPost and canReply are set based on canView
				if ($perm->getCategoryId() === 1) {
					$this->assertTrue($perm->getCanView());
					$this->assertTrue($perm->getCanPost());
					$this->assertTrue($perm->getCanReply());
					$this->assertFalse($perm->getCanModerate());
				} else {
					$this->assertTrue($perm->getCanView());
					$this->assertTrue($perm->getCanPost());
					$this->assertTrue($perm->getCanReply());
					$this->assertTrue($perm->getCanModerate());
				}
				return $perm;
			});

		$response = $this->controller->updatePermissions($roleId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsReturnsNotFoundWhenRoleDoesNotExist(): void {
		$roleId = 999;
		$permissions = [
			['categoryId' => 1, 'canView' => true, 'canModerate' => false],
		];

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($roleId)
			->willThrowException(new DoesNotExistException('Role not found'));

		$this->categoryPermMapper->expects($this->never())
			->method('deleteByRoleId');

		$this->categoryPermMapper->expects($this->never())
			->method('insert');

		$response = $this->controller->updatePermissions($roleId, $permissions);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Role not found'], $data);
	}

	public function testUpdateGuestRoleEnforcesNoAdminPermissions(): void {
		$guestRoleId = 4;
		$guestRole = $this->createRole($guestRoleId, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($guestRoleId)
			->willReturn($guestRole);

		$this->roleMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($role) use ($guestRoleId) {
				// Verify Guest role never has admin permissions
				$this->assertEquals($guestRoleId, $role->getId());
				$this->assertFalse($role->getCanAccessAdminTools());
				$this->assertFalse($role->getCanEditRoles());
				$this->assertFalse($role->getCanEditCategories());
				return $role;
			});

		// Try to update Guest role with permissions set to true - should be forced to false
		$response = $this->controller->update(
			$guestRoleId,
			'Guest',
			'Guest role',
			'#cccccc',
			'#cccccc',
			true,  // Try to enable - should be forced to false
			true,  // Try to enable - should be forced to false
			true   // Try to enable - should be forced to false
		);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['canAccessAdminTools']);
		$this->assertFalse($data['canEditRoles']);
		$this->assertFalse($data['canEditCategories']);
	}

	public function testUpdateGuestPermissionsEnforcesNoModerate(): void {
		$guestRoleId = 4;
		$guestRole = $this->createRole($guestRoleId, 'Guest', false, false, false, true, Role::ROLE_TYPE_GUEST);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($guestRoleId)
			->willReturn($guestRole);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByRoleId')
			->with($guestRoleId);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) use ($guestRoleId) {
				$this->assertEquals($guestRoleId, $perm->getRoleId());
				// Verify guest role never has moderate permission, even if requested
				$this->assertFalse($perm->getCanModerate());
				return $perm;
			});

		$permissions = [
			['categoryId' => 1, 'canView' => true, 'canModerate' => true],  // Try to enable moderate
			['categoryId' => 2, 'canView' => true, 'canModerate' => true],  // Try to enable moderate
		];

		$response = $this->controller->updatePermissions($guestRoleId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsEnforcesNoModerateForDefault(): void {
		$defaultRoleId = 3;
		$defaultRole = $this->createRole($defaultRoleId, 'User', false, false, false, true, Role::ROLE_TYPE_DEFAULT);

		$this->roleMapper->expects($this->once())
			->method('find')
			->with($defaultRoleId)
			->willReturn($defaultRole);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByRoleId')
			->with($defaultRoleId);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) use ($defaultRoleId) {
				$this->assertEquals($defaultRoleId, $perm->getRoleId());
				// Verify default role never has moderate permission, even if requested
				$this->assertFalse($perm->getCanModerate());
				return $perm;
			});

		$permissions = [
			['categoryId' => 1, 'canView' => true, 'canModerate' => true], // Try to enable moderate
			['categoryId' => 2, 'canView' => true, 'canModerate' => true], // Try to enable moderate
		];

		$response = $this->controller->updatePermissions($defaultRoleId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
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
}
