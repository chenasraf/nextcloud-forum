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
use OCA\Forum\Db\ReadMarker;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
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
	/** @var ReadMarkerMapper&MockObject */
	private ReadMarkerMapper $readMarkerMapper;
	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var PermissionService&MockObject */
	private PermissionService $permissionService;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
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
		$this->readMarkerMapper = $this->createMock(ReadMarkerMapper::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		// By default, grant access to all categories (tests that need filtering can override)
		$this->permissionService->method('getAccessibleCategories')
			->willReturnCallback(function () {
				// Return IDs 1-100 to cover all test categories
				return range(1, 100);
			});
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new CategoryController(
			Application::APP_ID,
			$this->request,
			$this->catHeaderMapper,
			$this->categoryMapper,
			$this->categoryPermMapper,
			$this->threadMapper,
			$this->readMarkerMapper,
			$this->roleMapper,
			$this->permissionService,
			$this->userSession,
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

	public function testIndexIncludesLastActivityAtFromThreadMapper(): void {
		$header = $this->createCatHeader(1, 'General');
		$category1 = $this->createCategory(1, 1, 'Announcements');
		$category2 = $this->createCategory(2, 1, 'Discussion');

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$category1, $category2]);

		$lastActivityMap = [1 => 1700000000, 2 => 1700001000];
		$this->threadMapper->expects($this->once())
			->method('getLastActivityByCategories')
			->willReturn($lastActivityMap);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$categories = $data[0]['categories'];
		$this->assertEquals(1700000000, $categories[0]['lastActivityAt']);
		$this->assertEquals(1700001000, $categories[1]['lastActivityAt']);
	}

	public function testIndexReturnsNullLastActivityAtWhenNoThreads(): void {
		$header = $this->createCatHeader(1, 'General');
		$category = $this->createCategory(1, 1, 'Empty Category');

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$category]);

		$this->threadMapper->method('getLastActivityByCategories')
			->willReturn([]); // No threads in any category

		$response = $this->controller->index();

		$data = $response->getData();
		$this->assertNull($data[0]['categories'][0]['lastActivityAt']);
	}

	public function testIndexIncludesReadAtForAuthenticatedUser(): void {
		$header = $this->createCatHeader(1, 'General');
		$category1 = $this->createCategory(1, 1, 'Announcements');
		$category2 = $this->createCategory(2, 1, 'Discussion');

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$category1, $category2]);
		$this->threadMapper->method('getLastActivityByCategories')->willReturn([]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$marker = new ReadMarker();
		$marker->setEntityId(1);
		$marker->setReadAt(1700000500);

		$this->readMarkerMapper->expects($this->once())
			->method('findCategoryMarkersByUserId')
			->with('user1')
			->willReturn([$marker]);

		$response = $this->controller->index();

		$data = $response->getData();
		$categories = $data[0]['categories'];
		$this->assertEquals(1700000500, $categories[0]['readAt']);
		$this->assertNull($categories[1]['readAt']); // No marker for category 2
	}

	public function testIndexReturnsNullReadAtForGuest(): void {
		$header = $this->createCatHeader(1, 'General');
		$category = $this->createCategory(1, 1, 'Announcements');

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$category]);
		$this->threadMapper->method('getLastActivityByCategories')->willReturn([]);

		$this->userSession->method('getUser')->willReturn(null);

		$this->readMarkerMapper->expects($this->never())
			->method('findCategoryMarkersByUserId');

		$response = $this->controller->index();

		$data = $response->getData();
		$this->assertNull($data[0]['categories'][0]['readAt']);
	}

	public function testIndexCategoryUnreadWhenActivityAfterReadMarker(): void {
		$header = $this->createCatHeader(1, 'General');
		$category = $this->createCategory(1, 1, 'Announcements');

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$category]);

		// Last post activity at timestamp 1000
		$this->threadMapper->method('getLastActivityByCategories')
			->willReturn([1 => 1700001000]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		// User last read at timestamp 500 (before activity)
		$marker = new ReadMarker();
		$marker->setEntityId(1);
		$marker->setReadAt(1700000500);

		$this->readMarkerMapper->method('findCategoryMarkersByUserId')
			->willReturn([$marker]);

		$response = $this->controller->index();

		$data = $response->getData();
		$cat = $data[0]['categories'][0];
		// lastActivityAt > readAt means unread
		$this->assertGreaterThan($cat['readAt'], $cat['lastActivityAt']);
	}

	public function testIndexCategoryReadWhenReadMarkerAfterActivity(): void {
		$header = $this->createCatHeader(1, 'General');
		$category = $this->createCategory(1, 1, 'Announcements');

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$category]);

		// Last post activity at timestamp 500
		$this->threadMapper->method('getLastActivityByCategories')
			->willReturn([1 => 1700000500]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		// User last read at timestamp 1000 (after activity)
		$marker = new ReadMarker();
		$marker->setEntityId(1);
		$marker->setReadAt(1700001000);

		$this->readMarkerMapper->method('findCategoryMarkersByUserId')
			->willReturn([$marker]);

		$response = $this->controller->index();

		$data = $response->getData();
		$cat = $data[0]['categories'][0];
		// readAt >= lastActivityAt means read
		$this->assertGreaterThanOrEqual($cat['lastActivityAt'], $cat['readAt']);
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

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, $categoryId, $permission)
			->willReturn(true);

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

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, $categoryId, $permission)
			->willReturn(false);

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

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, $categoryId, $permission)
			->willReturn(true);

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
		$perm1->setTargetType('role');
		$perm1->setTargetId('2');
		$perm1->setCanView(true);
		$perm1->setCanPost(true);
		$perm1->setCanReply(true);
		$perm1->setCanModerate(false);

		$perm2 = new CategoryPerm();
		$perm2->setId(2);
		$perm2->setCategoryId($categoryId);
		$perm2->setTargetType('role');
		$perm2->setTargetId('3');
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
		$this->assertEquals('role', $data[0]['targetType']);
		$this->assertEquals('2', $data[0]['targetId']);
		$this->assertTrue($data[0]['canView']);
		$this->assertFalse($data[0]['canModerate']);
	}

	public function testUpdatePermissionsSuccessfully(): void {
		$categoryId = 1;
		$permissions = [
			['roleId' => 2, 'canView' => true, 'canPost' => true, 'canModerate' => false],
			['roleId' => 3, 'canView' => true, 'canPost' => false, 'canModerate' => true],
		];

		$category = $this->createCategory($categoryId, 1, 'Test Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('deleteByCategoryIdAndTargetType')
			->with($categoryId, $this->anything());

		$this->roleMapper->expects($this->exactly(4))
			->method('find')
			->willReturnMap([
				[2, (function () {
					$r = new Role();
					$r->setId(2);
					$r->setRoleType(Role::ROLE_TYPE_MODERATOR);
					return $r;
				})()],
				[3, (function () {
					$r = new Role();
					$r->setId(3);
					$r->setRoleType(Role::ROLE_TYPE_MODERATOR);
					return $r;
				})()],
			]);

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) {
				if ($perm->getTargetId() === '2') {
					$this->assertTrue($perm->getCanPost());
					$this->assertTrue($perm->getCanReply());
				} else {
					$this->assertFalse($perm->getCanPost());
					$this->assertFalse($perm->getCanReply());
				}
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

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('deleteByCategoryIdAndTargetType')
			->with($categoryId, $this->anything());

		// Should only insert 2 permissions (Admin role ID 1 is filtered out)
		$this->categoryPermMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($perm) {
				// Verify that Admin role (ID 1) is never inserted
				$this->assertNotEquals('1', $perm->getTargetId());
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

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('deleteByCategoryIdAndTargetType')
			->with($categoryId, $this->anything());

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
				$this->assertEquals((string)$guestRoleId, $perm->getTargetId());
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

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('deleteByCategoryIdAndTargetType')
			->with($categoryId, $this->anything());

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
				$this->assertEquals((string)$moderatorRoleId, $perm->getTargetId());
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

		$this->categoryPermMapper->expects($this->exactly(2))
			->method('deleteByCategoryIdAndTargetType')
			->with($categoryId, $this->anything());

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
				$this->assertEquals((string)$defaultRoleId, $perm->getTargetId());
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

	// ====== Subcategory Tests ======

	public function testCreateCategoryWithParentId(): void {
		$parentCategory = $this->createCategory(1, 1, 'Parent Category');
		$createdChild = $this->createCategory(2, 1, 'Child Category', 1);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($parentCategory);

		$this->categoryMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($category) use ($createdChild) {
				// Child categories should have null headerId and parentId set
				$this->assertNull($category->getHeaderId());
				$this->assertEquals(1, $category->getParentId());
				return $createdChild;
			});

		$response = $this->controller->create(null, 'Child Category', 'child-category', null, 0, null, null, 1);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['parentId']);
	}

	public function testCreateCategoryWithParentIdNotFound(): void {
		$this->categoryMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException('Not found'));

		$response = $this->controller->create(null, 'Child', 'child', null, 0, null, null, 999);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateCategoryRequiresHeaderOrParent(): void {
		$response = $this->controller->create(null, 'Orphan', 'orphan');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUpdateCategorySetParentId(): void {
		$category = $this->createCategory(1, 1, 'Category');
		$parentCategory = $this->createCategory(2, 1, 'Parent');

		$this->categoryMapper->method('find')
			->willReturnMap([
				[1, $category],
				[2, $parentCategory],
			]);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($cat) {
				$this->assertEquals(2, $cat->getParentId());
				$this->assertNull($cat->getHeaderId());
				return $cat;
			});

		$response = $this->controller->update(1, null, null, null, null, null, '__unset__', '__unset__', '2');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateCategoryPreventCircularReferenceDirectChild(): void {
		$parentCategory = $this->createCategory(1, 1, 'Parent');
		$childCategory = $this->createCategory(2, 1, 'Child', 1);

		$this->categoryMapper->method('find')
			->willReturnMap([
				[1, $parentCategory],
				[2, $childCategory],
			]);

		// Try to set parent (1) as child of its own child (2)
		$response = $this->controller->update(1, null, null, null, null, null, '__unset__', '__unset__', '2');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertStringContainsString('circular', $data['error']);
	}

	public function testUpdateCategoryPreventCircularReferenceDeeperDescendant(): void {
		$grandparent = $this->createCategory(1, 1, 'Grandparent');
		$parent = $this->createCategory(2, 1, 'Parent', 1);
		$child = $this->createCategory(3, 1, 'Child', 2);

		$this->categoryMapper->method('find')
			->willReturnMap([
				[1, $grandparent],
				[2, $parent],
				[3, $child],
			]);

		// Try to set grandparent (1) as child of its grandchild (3)
		$response = $this->controller->update(1, null, null, null, null, null, '__unset__', '__unset__', '3');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertStringContainsString('circular', $data['error']);
	}

	public function testUpdateCategoryPreventSelfAsParent(): void {
		$category = $this->createCategory(1, 1, 'Category');

		$this->categoryMapper->method('find')
			->willReturnMap([
				[1, $category],
			]);

		// Try to set category as its own parent
		$response = $this->controller->update(1, null, null, null, null, null, '__unset__', '__unset__', '1');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertStringContainsString('circular', $data['error']);
	}

	public function testUpdateCategorySetHideChildrenOnCard(): void {
		$category = $this->createCategory(1, 1, 'Category');

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($cat) {
				$this->assertTrue($cat->getHideChildrenOnCard());
				return $cat;
			});

		$response = $this->controller->update(1, null, null, null, null, null, '__unset__', '__unset__', '__unset__', true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyReparentsChildren(): void {
		$parent = $this->createCategory(1, 1, 'Parent');
		$child1 = $this->createCategory(2, 1, 'Child 1', 1);
		$child2 = $this->createCategory(3, 1, 'Child 2', 1);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($parent);

		$this->categoryMapper->expects($this->once())
			->method('findByParentId')
			->with(1)
			->willReturn([$child1, $child2]);

		// Children should be re-parented to parent's parent (null = top-level)
		$updateCount = 0;
		$this->categoryMapper->method('update')
			->willReturnCallback(function ($cat) use (&$updateCount) {
				$updateCount++;
				$this->assertNull($cat->getParentId());
				$this->assertEquals(1, $cat->getHeaderId());
				return $cat;
			});

		$this->threadMapper->method('softDeleteByCategoryId')->willReturn(0);
		$this->categoryMapper->expects($this->once())->method('delete')->with($parent);

		$response = $this->controller->destroy(1);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(2, $updateCount);
	}

	public function testIndexGroupsChildCategoriesUnderParentHeader(): void {
		$header = $this->createCatHeader(1, 'General');
		$parent = $this->createCategory(1, 1, 'Parent');
		$child = $this->createCategory(2, 1, 'Child', 1);
		// Child has null headerId but should be grouped under header 1

		$this->catHeaderMapper->method('findAll')->willReturn([$header]);
		$this->categoryMapper->method('findAll')->willReturn([$parent, $child]);
		$this->threadMapper->method('getLastActivityByCategories')->willReturn([]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(1, $data); // One header
		$this->assertCount(2, $data[0]['categories']); // Both parent and child under it

		// Verify child has parentId set
		$childData = array_values(array_filter($data[0]['categories'], fn ($c) => $c['id'] === 2));
		$this->assertNotEmpty($childData);
		$this->assertEquals(1, $childData[0]['parentId']);
	}

	public function testCategoryEntityJsonIncludesSubcategoryFields(): void {
		$category = new Category();
		$category->setId(1);
		$category->setHeaderId(1);
		$category->setParentId(5);
		$category->setName('Test');
		$category->setSlug('test');
		$category->setSortOrder(0);
		$category->setHideChildrenOnCard(true);
		$category->setThreadCount(0);
		$category->setPostCount(0);
		$category->setCreatedAt(time());
		$category->setUpdatedAt(time());

		$json = $category->jsonSerialize();

		$this->assertArrayHasKey('parentId', $json);
		$this->assertEquals(5, $json['parentId']);
		$this->assertArrayHasKey('hideChildrenOnCard', $json);
		$this->assertTrue($json['hideChildrenOnCard']);
	}

	private function createCatHeader(int $id, string $name): CatHeader {
		$header = new CatHeader();
		$header->setId($id);
		$header->setName($name);
		$header->setSortOrder(0);
		$header->setCreatedAt(time());
		return $header;
	}

	private function createCategory(int $id, int $headerId, string $name, ?int $parentId = null): Category {
		$category = new Category();
		$category->setId($id);
		$category->setHeaderId($parentId !== null ? null : $headerId);
		$category->setParentId($parentId);
		$category->setName($name);
		$category->setSlug("category-$id");
		$category->setDescription(null);
		$category->setSortOrder(0);
		$category->setHideChildrenOnCard(false);
		$category->setThreadCount(0);
		$category->setPostCount(0);
		$category->setCreatedAt(time());
		$category->setUpdatedAt(time());
		return $category;
	}
}
