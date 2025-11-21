<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\CategoryController;
use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\CatHeader;
use OCA\Forum\Db\CatHeaderMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryControllerTest extends TestCase {
	private CategoryController $controller;
	private CatHeaderMapper $catHeaderMapper;
	private CategoryMapper $categoryMapper;
	private CategoryPermMapper $categoryPermMapper;
	private ThreadMapper $threadMapper;
	private UserRoleMapper $userRoleMapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->catHeaderMapper = $this->createMock(CatHeaderMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->categoryPermMapper = $this->createMock(CategoryPermMapper::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->userRoleMapper = $this->createMock(UserRoleMapper::class);
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
			$this->userRoleMapper,
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

		$response = $this->controller->update($categoryId, $newName);

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

		$response = $this->controller->update($categoryId, $newName, $newDescription, $newSlug, $newSortOrder);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateCategoryReturnsNotFoundWhenCategoryDoesNotExist(): void {
		$categoryId = 999;

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$response = $this->controller->update($categoryId, 'New Name');

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
