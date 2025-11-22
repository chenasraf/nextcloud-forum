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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RoleControllerTest extends TestCase {
	private RoleController $controller;
	private RoleMapper $roleMapper;
	private CategoryPermMapper $categoryPermMapper;
	private LoggerInterface $logger;
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
		$adminRole = $this->createRole($adminRoleId, 'Admin', true, true, true);

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
		$role = $this->createRole($roleId, 'Moderator', true, false, true);

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

		// Should not even try to find the role - should reject immediately
		$this->roleMapper->expects($this->never())
			->method('find');

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($adminRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'System roles cannot be deleted'], $data);
	}

	public function testDeleteModeratorRoleReturnsForbidden(): void {
		$moderatorRoleId = 2;

		$this->roleMapper->expects($this->never())
			->method('find');

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($moderatorRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'System roles cannot be deleted'], $data);
	}

	public function testDeleteUserRoleReturnsForbidden(): void {
		$userRoleId = 3;

		$this->roleMapper->expects($this->never())
			->method('find');

		$this->roleMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($userRoleId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'System roles cannot be deleted'], $data);
	}

	public function testDeleteCustomRoleSuccessfully(): void {
		$customRoleId = 4;
		$customRole = $this->createRole($customRoleId, 'Custom Role', false, false, false);

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
		$role1 = $this->createRole(1, 'Admin', true, true, true);
		$role2 = $this->createRole(2, 'Moderator', true, false, true);
		$role3 = $this->createRole(3, 'User', false, false, false);

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
		$role = $this->createRole($roleId, 'Moderator', true, false, true);

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

		$role = $this->createRole($roleId, 'Moderator', true, false, true);

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
}
