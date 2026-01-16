<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\CategoryController;
use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\CategoryPerm;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\CatHeader;
use OCA\Forum\Db\CatHeaderMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\ThreadMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryControllerTest extends TestCase {
	private CategoryController $controller;
	/** @var CatHeaderMapper&MockObject */
	private CatHeaderMapper $catHeaderMapper;
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $categoryMapper;
	/** @var CategoryPermMapper&MockObject */
	private CategoryPermMapper $categoryPermMapper;
	/** @var ThreadMapper&MockObject */
	private ThreadMapper $threadMapper;
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var IGroupManager&MockObject */
	private IGroupManager $groupManager;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->catHeaderMapper = $this->createMock(CatHeaderMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->categoryPermMapper = $this->createMock(CategoryPermMapper::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new CategoryController(
			Application::APP_ID,
			$this->request,
			$this->catHeaderMapper,
			$this->categoryMapper,
			$this->categoryPermMapper,
			$this->threadMapper,
			$this->roleMapper,
			$this->userSession,
			$this->groupManager,
			$this->logger
		);
	}

	public function testIndexReturnsHeadersWithNestedCategories(): void {
		$header1 = $this->createCatHeader(1, 'General');
		$header2 = $this->createCatHeader(2, 'Support');

		$category1 = $this->createCategory(1, 1, 'Announcements');
		$category2 = $this->createCategory(2, 1, 'General Discussion');
		$category3 = $this->createCategory(3, 2, 'Help Desk');

		$this->catHeaderMapper->expects($this->once())
			->method('findAll')
			->willReturn([$header1, $header2]);

		$this->categoryMapper->expects($this->once())
			->method('findAll')
			->willReturn([$category1, $category2, $category3]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertArrayHasKey('categories', $data[0]);
		$this->assertCount(2, $data[0]['categories']);
		$this->assertCount(1, $data[1]['categories']);
	}

	public function testByHeaderReturnsCategoriesForHeader(): void {
		$headerId = 1;
		$category1 = $this->createCategory(1, $headerId, 'Category 1');
		$category2 = $this->createCategory(2, $headerId, 'Category 2');

		$this->categoryMapper->expects($this->once())
			->method('findByHeaderId')
			->with($headerId)
			->willReturn([$category1, $category2]);

		$response = $this->controller->byHeader($headerId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testShowReturnsCategorySuccessfully(): void {
		$categoryId = 1;
		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$response = $this->controller->show($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($categoryId, $data['id']);
		$this->assertEquals('Test Category', $data['name']);
	}

	public function testShowReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$categoryId = 999;

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->show($categoryId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Category not found'], $response->getData());
	}

	public function testBySlugReturnsCategorySuccessfully(): void {
		$slug = 'test-category';
		$category = $this->createCategory(1, 1, 'Test Category');
		$category->setSlug($slug);

		$this->categoryMapper->expects($this->once())
			->method('findBySlug')
			->with($slug)
			->willReturn($category);

		$response = $this->controller->bySlug($slug);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($slug, $data['slug']);
	}

	public function testBySlugReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$slug = 'non-existent-category';

		$this->categoryMapper->expects($this->once())
			->method('findBySlug')
			->with($slug)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->bySlug($slug);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Category not found'], $response->getData());
	}

	public function testCreateCategorySuccessfully(): void {
		$headerId = 1;
		$name = 'New Category';
		$slug = 'new-category';
		$description = 'A new category';
		$sortOrder = 10;

		$createdCategory = $this->createCategory(1, $headerId, $name);
		$createdCategory->setSlug($slug);
		$createdCategory->setDescription($description);
		$createdCategory->setSortOrder($sortOrder);

		$this->categoryMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($category) use ($createdCategory) {
				return $createdCategory;
			});

		$response = $this->controller->create($headerId, $name, $slug, $description, $sortOrder);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['id']);
		$this->assertEquals($name, $data['name']);
		$this->assertEquals($slug, $data['slug']);
		$this->assertEquals($description, $data['description']);
		$this->assertEquals($sortOrder, $data['sortOrder']);
	}

	public function testUpdateCategorySuccessfully(): void {
		$categoryId = 1;
		$newName = 'Updated Category';
		$category = $this->createCategory($categoryId, 1, 'Original Name');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedCategory) use ($newName) {
				$this->assertEquals($newName, $updatedCategory->getName());
				return $updatedCategory;
			});

		$response = $this->controller->update($categoryId, null, $newName);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($categoryId, $data['id']);
	}

	public function testUpdateCategoryWithMultipleFields(): void {
		$categoryId = 1;
		$newName = 'Updated Name';
		$newDescription = 'Updated Description';
		$newSlug = 'updated-slug';
		$newSortOrder = 20;

		$category = $this->createCategory($categoryId, 1, 'Original Name');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedCategory) use ($newName, $newDescription, $newSlug, $newSortOrder) {
				$this->assertEquals($newName, $updatedCategory->getName());
				$this->assertEquals($newDescription, $updatedCategory->getDescription());
				$this->assertEquals($newSlug, $updatedCategory->getSlug());
				$this->assertEquals($newSortOrder, $updatedCategory->getSortOrder());
				return $updatedCategory;
			});

		$response = $this->controller->update($categoryId, null, $newName, $newDescription, $newSlug, $newSortOrder);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateCategoryHeaderId(): void {
		$categoryId = 1;
		$originalHeaderId = 1;
		$newHeaderId = 2;

		$category = $this->createCategory($categoryId, $originalHeaderId, 'Test Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedCategory) use ($newHeaderId) {
				$this->assertEquals($newHeaderId, $updatedCategory->getHeaderId());
				return $updatedCategory;
			});

		$response = $this->controller->update($categoryId, $newHeaderId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($newHeaderId, $data['headerId']);
	}

	public function testUpdateCategoryReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$categoryId = 999;

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->update($categoryId, null, 'New Name');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Category not found'], $response->getData());
	}

	public function testGetThreadCountReturnsCountSuccessfully(): void {
		$categoryId = 1;
		$expectedCount = 42;
		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->threadMapper->expects($this->once())
			->method('countByCategoryId')
			->with($categoryId)
			->willReturn($expectedCount);

		$response = $this->controller->getThreadCount($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['count' => $expectedCount], $data);
	}

	public function testGetThreadCountReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$categoryId = 999;

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->getThreadCount($categoryId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Category not found'], $response->getData());
	}

	public function testDestroyCategorySuccessfullyWithMigration(): void {
		$categoryId = 1;
		$targetCategoryId = 2;
		$threadsAffected = 5;

		$category = $this->createCategory($categoryId, 1, 'Category to Delete');
		$targetCategory = $this->createCategory($targetCategoryId, 1, 'Target Category');

		$this->categoryMapper->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[$categoryId, $category],
				[$targetCategoryId, $targetCategory],
			]);

		$this->threadMapper->expects($this->once())
			->method('moveToCategoryId')
			->with($categoryId, $targetCategoryId)
			->willReturn($threadsAffected);

		$this->categoryMapper->expects($this->once())
			->method('delete')
			->with($category);

		$response = $this->controller->destroy($categoryId, $targetCategoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals($threadsAffected, $data['threadsAffected']);
	}

	public function testDestroyCategorySuccessfullyWithSoftDelete(): void {
		$categoryId = 1;
		$threadsAffected = 3;

		$category = $this->createCategory($categoryId, 1, 'Category to Delete');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->threadMapper->expects($this->once())
			->method('softDeleteByCategoryId')
			->with($categoryId)
			->willReturn($threadsAffected);

		$this->categoryMapper->expects($this->once())
			->method('delete')
			->with($category);

		$response = $this->controller->destroy($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertEquals($threadsAffected, $data['threadsAffected']);
	}

	public function testDestroyCategoryReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$categoryId = 999;

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->destroy($categoryId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Category not found'], $response->getData());
	}

	public function testDestroyCategoryReturnsNotFoundWhenTargetCategoryDoesNotExist(): void {
		$categoryId = 1;
		$targetCategoryId = 999;

		$category = $this->createCategory($categoryId, 1, 'Category to Delete');

		$this->categoryMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(function ($id) use ($categoryId, $category, $targetCategoryId) {
				if ($id === $categoryId) {
					return $category;
				}
				if ($id === $targetCategoryId) {
					throw new DoesNotExistException('Target category not found');
				}
			});

		$response = $this->controller->destroy($categoryId, $targetCategoryId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Target category not found'], $response->getData());
	}

	public function testCheckPermissionReturnsTrue(): void {
		$categoryId = 1;
		$permission = 'canView';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// User is not an admin
		$this->groupManager->method('get')->with('admin')->willReturn(null);

		// User has a role
		$role = new Role();
		$role->setId(1);
		$role->setName('User');

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$role]);

		// Category permission allows viewing
		$categoryPerm = new CategoryPerm();
		$categoryPerm->setId(1);
		$categoryPerm->setCategoryId($categoryId);
		$categoryPerm->setRoleId(1);
		$categoryPerm->setCanView(true);
		$categoryPerm->setCanPost(false);
		$categoryPerm->setCanReply(false);
		$categoryPerm->setCanModerate(false);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRoles')
			->with($categoryId, [1])
			->willReturn([$categoryPerm]);

		$response = $this->controller->checkPermission($categoryId, $permission);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['hasPermission']);
	}

	public function testCheckPermissionReturnsFalseWhenNoPermission(): void {
		$categoryId = 1;
		$permission = 'canModerate';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->groupManager->method('get')->with('admin')->willReturn(null);

		$role = new Role();
		$role->setId(1);
		$role->setName('User');

		$this->roleMapper->expects($this->once())
			->method('findByUserId')
			->willReturn([$role]);

		// Category permission does not allow moderating
		$categoryPerm = new CategoryPerm();
		$categoryPerm->setId(1);
		$categoryPerm->setCategoryId($categoryId);
		$categoryPerm->setRoleId(1);
		$categoryPerm->setCanView(true);
		$categoryPerm->setCanPost(false);
		$categoryPerm->setCanReply(false);
		$categoryPerm->setCanModerate(false);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryAndRoles')
			->with($categoryId, [1])
			->willReturn([$categoryPerm]);

		$response = $this->controller->checkPermission($categoryId, $permission);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['hasPermission']);
	}

	public function testCheckPermissionReturnsTrueForAdmin(): void {
		$categoryId = 1;
		$permission = 'canModerate';
		$userId = 'admin1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// User is in admin group
		$adminGroup = $this->createMock(IGroup::class);
		$adminGroup->method('inGroup')->with($user)->willReturn(true);
		$this->groupManager->method('get')->with('admin')->willReturn($adminGroup);

		$response = $this->controller->checkPermission($categoryId, $permission);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['hasPermission']);
	}

	public function testGetPermissionsReturnsPermissionsSuccessfully(): void {
		$categoryId = 1;

		// Note: Only non-admin roles (2, 3) are returned - Admin role is excluded
		$perm1 = new CategoryPerm();
		$perm1->setId(1);
		$perm1->setCategoryId($categoryId);
		$perm1->setRoleId(2);
		$perm1->setCanView(true);
		$perm1->setCanPost(true);
		$perm1->setCanReply(true);
		$perm1->setCanModerate(false);

		$perm2 = new CategoryPerm();
		$perm2->setId(2);
		$perm2->setCategoryId($categoryId);
		$perm2->setRoleId(3);
		$perm2->setCanView(true);
		$perm2->setCanPost(false);
		$perm2->setCanReply(false);
		$perm2->setCanModerate(false);

		$this->categoryPermMapper->expects($this->once())
			->method('findByCategoryIdExcludingAdmin')
			->with($categoryId)
			->willReturn([$perm1, $perm2]);

		$response = $this->controller->getPermissions($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals(2, $data[0]['roleId']);
		$this->assertTrue($data[0]['canView']);
		$this->assertFalse($data[0]['canModerate']);
	}

	public function testUpdatePermissionsSuccessfully(): void {
		$categoryId = 1;
		$permissions = [
			['roleId' => 2, 'canView' => true, 'canModerate' => false],
			['roleId' => 3, 'canView' => true, 'canModerate' => true],
		];

		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByCategoryId')
			->with($categoryId);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) {
				return $perm;
			});

		$response = $this->controller->updatePermissions($categoryId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsFiltersOutAdminRole(): void {
		$categoryId = 1;
		$permissions = [
			['roleId' => 1, 'canView' => true, 'canModerate' => true], // Admin - should be filtered
			['roleId' => 2, 'canView' => true, 'canModerate' => false],
			['roleId' => 3, 'canView' => true, 'canModerate' => false],
		];

		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		// Mock role lookups for admin check
		$adminRole = new Role();
		$adminRole->setId(1);
		$adminRole->setName('Admin');
		$adminRole->setRoleType(Role::ROLE_TYPE_ADMIN);

		$moderatorRole = new Role();
		$moderatorRole->setId(2);
		$moderatorRole->setName('Moderator');
		$moderatorRole->setRoleType(Role::ROLE_TYPE_MODERATOR);

		$userRole = new Role();
		$userRole->setId(3);
		$userRole->setName('User');
		$userRole->setRoleType(Role::ROLE_TYPE_DEFAULT);

		// roleMapper->find() is called:
		// - 3 times during filtering phase (for roles 1, 2, 3)
		// - 2 times during insertion phase (for roles 2, 3 only - role 1 is filtered out)
		$this->roleMapper->expects($this->exactly(5))
			->method('find')
			->willReturnMap([
				[1, $adminRole],
				[2, $moderatorRole],
				[3, $userRole],
			]);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByCategoryId')
			->with($categoryId);

		// Should only insert 2 permissions (Admin role ID 1 is filtered out)
		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) {
				// Verify that Admin role (ID 1) is never inserted
				$this->assertNotEquals(1, $perm->getRoleId());
				return $perm;
			});

		$response = $this->controller->updatePermissions($categoryId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$categoryId = 999;
		$permissions = [
			['roleId' => 1, 'canView' => true, 'canModerate' => false],
		];

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->updatePermissions($categoryId, $permissions);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Category not found'], $response->getData());
	}

	public function testReorderUpdatesCategories(): void {
		$categories = [
			['id' => 1, 'sortOrder' => 2],
			['id' => 2, 'sortOrder' => 1],
		];

		$category1 = $this->createCategory(1, 1, 'Category 1');
		$category2 = $this->createCategory(2, 1, 'Category 2');

		$this->categoryMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(function ($id) use ($category1, $category2) {
				return $id === 1 ? $category1 : $category2;
			});

		$this->categoryMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function ($category) use ($categories) {
				if ($category->getId() === 1) {
					$this->assertEquals(2, $category->getSortOrder());
				} else {
					$this->assertEquals(1, $category->getSortOrder());
				}
				return $category;
			});

		$response = $this->controller->reorder($categories);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsEnforcesNoModerateForGuest(): void {
		$categoryId = 1;
		$guestRoleId = 4;

		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$guestRole = new Role();
		$guestRole->setId($guestRoleId);
		$guestRole->setName('Guest');
		$guestRole->setRoleType(Role::ROLE_TYPE_GUEST);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByCategoryId')
			->with($categoryId);

		// roleMapper->find() is called twice:
		// - Once during filtering phase
		// - Once during insertion phase
		$this->roleMapper->expects($this->exactly(2))
			->method('find')
			->with($guestRoleId)
			->willReturn($guestRole);

		$this->categoryPermMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($perm) use ($guestRoleId, $categoryId) {
				$this->assertEquals($categoryId, $perm->getCategoryId());
				$this->assertEquals($guestRoleId, $perm->getRoleId());
				$this->assertTrue($perm->getCanView());
				// Verify guest role never has moderate permission, even if requested
				$this->assertFalse($perm->getCanModerate());
				return $perm;
			});

		$permissions = [
			['roleId' => $guestRoleId, 'canView' => true, 'canModerate' => true], // Try to enable moderate
		];

		$response = $this->controller->updatePermissions($categoryId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsAllowsModerateForNonGuest(): void {
		$categoryId = 1;
		$moderatorRoleId = 2;

		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$moderatorRole = new Role();
		$moderatorRole->setId($moderatorRoleId);
		$moderatorRole->setName('Moderator');
		$moderatorRole->setRoleType(Role::ROLE_TYPE_MODERATOR);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByCategoryId')
			->with($categoryId);

		// roleMapper->find() is called twice:
		// - Once during filtering phase
		// - Once during insertion phase
		$this->roleMapper->expects($this->exactly(2))
			->method('find')
			->with($moderatorRoleId)
			->willReturn($moderatorRole);

		$this->categoryPermMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($perm) use ($moderatorRoleId, $categoryId) {
				$this->assertEquals($categoryId, $perm->getCategoryId());
				$this->assertEquals($moderatorRoleId, $perm->getRoleId());
				$this->assertTrue($perm->getCanView());
				// Verify non-guest role CAN have moderate permission
				$this->assertTrue($perm->getCanModerate());
				return $perm;
			});

		$permissions = [
			['roleId' => $moderatorRoleId, 'canView' => true, 'canModerate' => true],
		];

		$response = $this->controller->updatePermissions($categoryId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUpdatePermissionsEnforcesNoModerateForDefault(): void {
		$categoryId = 1;
		$defaultRoleId = 3;

		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$defaultRole = new Role();
		$defaultRole->setId($defaultRoleId);
		$defaultRole->setName('User');
		$defaultRole->setRoleType(Role::ROLE_TYPE_DEFAULT);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryPermMapper->expects($this->once())
			->method('deleteByCategoryId')
			->with($categoryId);

		// roleMapper->find() is called twice:
		// - Once during filtering phase
		// - Once during insertion phase
		$this->roleMapper->expects($this->exactly(2))
			->method('find')
			->with($defaultRoleId)
			->willReturn($defaultRole);

		$this->categoryPermMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($perm) use ($defaultRoleId, $categoryId) {
				$this->assertEquals($categoryId, $perm->getCategoryId());
				$this->assertEquals($defaultRoleId, $perm->getRoleId());
				$this->assertTrue($perm->getCanView());
				// Verify default role never has moderate permission, even if requested
				$this->assertFalse($perm->getCanModerate());
				return $perm;
			});

		$permissions = [
			['roleId' => $defaultRoleId, 'canView' => true, 'canModerate' => true], // Try to enable moderate
		];

		$response = $this->controller->updatePermissions($categoryId, $permissions);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	private function createCatHeader(int $id, string $name): CatHeader {
		$header = new CatHeader();
		$header->setId($id);
		$header->setName($name);
		$header->setSortOrder(0);
		$header->setCreatedAt(time());
		return $header;
	}

	private function createCategory(int $id, int $headerId, string $name): Category {
		$category = new Category();
		$category->setId($id);
		$category->setHeaderId($headerId);
		$category->setName($name);
		$category->setSlug("category-$id");
		$category->setDescription(null);
		$category->setSortOrder(0);
		$category->setThreadCount(0);
		$category->setPostCount(0);
		$category->setCreatedAt(time());
		$category->setUpdatedAt(time());
		return $category;
	}
}
