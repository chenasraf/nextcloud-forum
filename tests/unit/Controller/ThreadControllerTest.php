<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ThreadController;
use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ForumUser;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ThreadControllerTest extends TestCase {
	private ThreadController $controller;
	private ThreadMapper $threadMapper;
	private CategoryMapper $categoryMapper;
	private PostMapper $postMapper;
	private ForumUserMapper $forumUserMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->forumUserMapper = $this->createMock(ForumUserMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ThreadController(
			Application::APP_ID,
			$this->request,
			$this->threadMapper,
			$this->categoryMapper,
			$this->postMapper,
			$this->forumUserMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testIndexReturnsAllThreadsSuccessfully(): void {
		$thread1 = $this->createMockThread(1, 1, 'user1', 'Test Thread 1');
		$thread2 = $this->createMockThread(2, 1, 'user2', 'Test Thread 2');

		$this->threadMapper->expects($this->once())
			->method('findAll')
			->willReturn([$thread1, $thread2]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testByCategoryReturnsThreadsSuccessfully(): void {
		$categoryId = 1;
		$limit = 50;
		$offset = 0;

		$thread1 = $this->createMockThread(1, $categoryId, 'user1', 'Test Thread 1');
		$thread2 = $this->createMockThread(2, $categoryId, 'user2', 'Test Thread 2');

		$this->threadMapper->expects($this->once())
			->method('findByCategoryId')
			->with($categoryId, $limit, $offset)
			->willReturn([$thread1, $thread2]);

		$response = $this->controller->byCategory($categoryId, $limit, $offset);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testShowReturnsThreadAndIncrementsViewCount(): void {
		$threadId = 1;
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Test Thread');
		$initialViewCount = $thread->getViewCount();

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) use ($initialViewCount) {
				$this->assertEquals($initialViewCount + 1, $updatedThread->getViewCount());
				return $updatedThread;
			});

		$response = $this->controller->show($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['id']);
	}

	public function testShowReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->show($threadId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testBySlugReturnsThreadAndIncrementsViewCount(): void {
		$slug = 'test-thread';
		$thread = $this->createMockThread(1, 1, 'user1', 'Test Thread');
		$thread->setSlug($slug);
		$initialViewCount = $thread->getViewCount();

		$this->threadMapper->expects($this->once())
			->method('findBySlug')
			->with($slug)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) use ($initialViewCount) {
				$this->assertEquals($initialViewCount + 1, $updatedThread->getViewCount());
				return $updatedThread;
			});

		$response = $this->controller->bySlug($slug);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($slug, $data['slug']);
	}

	public function testBySlugReturnsNotFoundWhenThreadDoesNotExist(): void {
		$slug = 'non-existent-thread';

		$this->threadMapper->expects($this->once())
			->method('findBySlug')
			->with($slug)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->bySlug($slug);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testCreateThreadSuccessfully(): void {
		$categoryId = 1;
		$title = 'New Thread';
		$content = 'This is the initial post content';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$forumUser = new ForumUser();
		$forumUser->setUserId($userId);
		$forumUser->setPostCount(10);

		$this->forumUserMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn($forumUser);

		$category = new Category();
		$category->setId($categoryId);
		$category->setThreadCount(5);
		$category->setPostCount(20);

		$createdThread = $this->createMockThread(1, $categoryId, $userId, $title);
		$createdThread->setSlug('new-thread');

		$createdPost = new Post();
		$createdPost->setId(1);
		$createdPost->setThreadId(1);
		$createdPost->setAuthorId($userId);
		$createdPost->setContent($content);

		// Mock findBySlug to return DoesNotExistException (slug doesn't exist yet)
		$this->threadMapper->expects($this->once())
			->method('findBySlug')
			->willThrowException(new DoesNotExistException('Thread not found'));

		$this->threadMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($thread) use ($createdThread) {
				return $createdThread;
			});

		$this->postMapper->expects($this->once())
			->method('insert')
			->willReturn($createdPost);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($thread) {
				$this->assertEquals(1, $thread->getPostCount());
				return $thread;
			});

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedCategory) {
				$this->assertEquals(6, $updatedCategory->getThreadCount());
				$this->assertEquals(21, $updatedCategory->getPostCount());
				return $updatedCategory;
			});

		$this->forumUserMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedUser) {
				$this->assertEquals(11, $updatedUser->getPostCount());
				return $updatedUser;
			});

		$response = $this->controller->create($categoryId, $title, $content);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['id']);
		$this->assertEquals($categoryId, $data['categoryId']);
		$this->assertEquals($userId, $data['authorId']);
		$this->assertEquals($title, $data['title']);
		$this->assertNotEmpty($data['slug']);
		$this->assertEquals('new-thread', $data['slug']);
	}

	public function testCreateThreadReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$categoryId = 1;
		$title = 'New Thread';
		$content = 'Initial post content';

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create($categoryId, $title, $content);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testCreateThreadReturnsForbiddenWhenForumUserNotRegistered(): void {
		$categoryId = 1;
		$title = 'New Thread';
		$content = 'Initial post content';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willThrowException(new DoesNotExistException('User not found'));

		$response = $this->controller->create($categoryId, $title, $content);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertEquals(['error' => 'User not registered in forum'], $response->getData());
	}

	public function testUpdateThreadSuccessfully(): void {
		$threadId = 1;
		$newTitle = 'Updated Title';
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Original Title');

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) use ($newTitle) {
				$this->assertEquals($newTitle, $updatedThread->getTitle());
				return $updatedThread;
			});

		$response = $this->controller->update($threadId, $newTitle);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['id']);
	}

	public function testUpdateThreadLockedStatus(): void {
		$threadId = 1;
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Test Thread');

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				$this->assertTrue($updatedThread->getIsLocked());
				return $updatedThread;
			});

		$response = $this->controller->update($threadId, null, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateThreadPinnedStatus(): void {
		$threadId = 1;
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Test Thread');

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				$this->assertTrue($updatedThread->getIsPinned());
				return $updatedThread;
			});

		$response = $this->controller->update($threadId, null, null, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateThreadHiddenStatus(): void {
		$threadId = 1;
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Test Thread');

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				$this->assertTrue($updatedThread->getIsHidden());
				return $updatedThread;
			});

		$response = $this->controller->update($threadId, null, null, null, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateThreadReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->update($threadId, 'New Title');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testDestroyThreadSuccessfully(): void {
		$threadId = 1;
		$categoryId = 1;
		$thread = $this->createMockThread($threadId, $categoryId, 'user1', 'Test Thread');

		// Mock category
		$category = new Category();
		$category->setId($categoryId);
		$category->setSlug('test-category');
		$category->setThreadCount(5);
		$category->setPostCount(20);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($categoryId)
			->willReturn($category);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				$this->assertNotNull($updatedThread->getDeletedAt());
				return $updatedThread;
			});

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturn($category);

		$response = $this->controller->destroy($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['success' => true, 'categorySlug' => 'test-category'], $response->getData());
	}

	public function testDestroyThreadReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->destroy($threadId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	private function createMockThread(int $id, int $categoryId, string $authorId, string $title): Thread {
		$thread = new Thread();
		$thread->setId($id);
		$thread->setCategoryId($categoryId);
		$thread->setAuthorId($authorId);
		$thread->setTitle($title);
		$thread->setSlug("thread-$id");
		$thread->setViewCount(0);
		$thread->setPostCount(0);
		$thread->setIsLocked(false);
		$thread->setIsPinned(false);
		$thread->setIsHidden(false);
		$thread->setCreatedAt(time());
		$thread->setUpdatedAt(time());
		return $thread;
	}
}
