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
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCA\Forum\Service\NotificationService;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\ThreadEnrichmentService;
use OCA\Forum\Service\UserPreferencesService;
use OCA\Forum\Service\UserService;
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
	private ThreadSubscriptionMapper $threadSubscriptionMapper;
	private ThreadEnrichmentService $threadEnrichmentService;
	private UserPreferencesService $userPreferencesService;
	private UserService $userService;
	private PermissionService $permissionService;
	private NotificationService $notificationService;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->forumUserMapper = $this->createMock(ForumUserMapper::class);
		$this->threadSubscriptionMapper = $this->createMock(ThreadSubscriptionMapper::class);
		$this->threadEnrichmentService = $this->createMock(ThreadEnrichmentService::class);
		$this->userPreferencesService = $this->createMock(UserPreferencesService::class);
		$this->userService = $this->createMock(UserService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->notificationService = $this->createMock(NotificationService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		// Mock thread enrichment service to return serialized thread with mock data
		$this->threadEnrichmentService->method('enrichThread')
			->willReturnCallback(function ($thread) {
				$data = is_array($thread) ? $thread : $thread->jsonSerialize();
				$data['author'] = ['userId' => $data['authorId'], 'displayName' => 'Test User'];
				$data['categorySlug'] = 'test-category';
				$data['categoryName'] = 'Test Category';
				$data['isSubscribed'] = false;
				return $data;
			});

		$this->controller = new ThreadController(
			Application::APP_ID,
			$this->request,
			$this->threadMapper,
			$this->categoryMapper,
			$this->postMapper,
			$this->forumUserMapper,
			$this->threadSubscriptionMapper,
			$this->threadEnrichmentService,
			$this->userPreferencesService,
			$this->userService,
			$this->permissionService,
			$this->notificationService,
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

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->willReturn([
				'user1' => ['userId' => 'user1', 'displayName' => 'User 1'],
				'user2' => ['userId' => 'user2', 'displayName' => 'User 2'],
			]);

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

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->willReturn([
				'user1' => ['userId' => 'user1', 'displayName' => 'User 1'],
				'user2' => ['userId' => 'user2', 'displayName' => 'User 2'],
			]);

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

		// Mock forum user increment methods (first post doesn't count, only thread count increments)
		$this->forumUserMapper->expects($this->never())
			->method('incrementPostCount');
		$this->forumUserMapper->expects($this->once())
			->method('incrementThreadCount');

		// Mock thread subscription
		$this->userPreferencesService->method('getPreference')->willReturn(false);

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
				// First post doesn't count, so postCount should be 0
				$this->assertEquals(0, $thread->getPostCount());
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
				// First post doesn't count, so postCount stays at 20
				$this->assertEquals(20, $updatedCategory->getPostCount());
				return $updatedCategory;
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

	public function testCreateThreadSucceedsEvenWhenForumUserUpdateFails(): void {
		$categoryId = 1;
		$title = 'New Thread';
		$content = 'Initial post content';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// Mock forum user methods to throw exceptions (simulating forum user update failure)
		// The controller catches these and just logs warnings, so thread creation should still succeed
		$this->forumUserMapper->method('incrementPostCount')
			->willThrowException(new \Exception('Failed to increment post count'));
		$this->forumUserMapper->method('incrementThreadCount')
			->willThrowException(new \Exception('Failed to increment thread count'));

		// Mock thread subscription
		$this->userPreferencesService->method('getPreference')
			->willReturn(false);

		// Mock thread creation parts
		$this->threadMapper->method('findBySlug')->willThrowException(new DoesNotExistException(''));
		$thread = $this->createMockThread(1, $categoryId, $userId, $title);
		$this->threadMapper->method('insert')->willReturn($thread);
		$post = new Post();
		$post->setId(1);
		$this->postMapper->method('insert')->willReturn($post);
		$this->threadMapper->method('update')->willReturn($thread);
		$category = new Category();
		$category->setThreadCount(0);
		$category->setPostCount(0);
		$this->categoryMapper->method('find')->willReturn($category);
		$this->categoryMapper->method('update')->willReturn($category);

		$response = $this->controller->create($categoryId, $title, $content);

		// Thread creation should succeed even if forum user update fails (they're in a try-catch)
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testUpdateThreadTitleSuccessfullyAsAuthor(): void {
		$threadId = 1;
		$categoryId = 1;
		$authorId = 'user1';
		$newTitle = 'Updated Title';
		$thread = $this->createMockThread($threadId, $categoryId, $authorId, 'Original Title');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($authorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($authorId, $categoryId, 'canModerate')
			->willReturn(false);

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

	public function testUpdateThreadTitleSuccessfullyAsModerator(): void {
		$threadId = 1;
		$categoryId = 1;
		$authorId = 'user1';
		$moderatorId = 'moderator1';
		$newTitle = 'Updated Title';
		$thread = $this->createMockThread($threadId, $categoryId, $authorId, 'Original Title');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($moderatorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($moderatorId, $categoryId, 'canModerate')
			->willReturn(true);

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

	public function testUpdateThreadTitleReturnsForbiddenWhenUserIsNeitherAuthorNorModerator(): void {
		$threadId = 1;
		$categoryId = 1;
		$authorId = 'user1';
		$otherUserId = 'user2';
		$newTitle = 'Updated Title';
		$thread = $this->createMockThread($threadId, $categoryId, $authorId, 'Original Title');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($otherUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($otherUserId, $categoryId, 'canModerate')
			->willReturn(false);

		$this->threadMapper->expects($this->never())
			->method('update');

		$response = $this->controller->update($threadId, $newTitle);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertEquals(['error' => 'You do not have permission to edit this thread title'], $response->getData());
	}

	public function testUpdateThreadLockedStatus(): void {
		$threadId = 1;
		$categoryId = 1;
		$moderatorId = 'moderator1';
		$thread = $this->createMockThread($threadId, $categoryId, 'user1', 'Test Thread');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($moderatorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($moderatorId, $categoryId, 'canModerate')
			->willReturn(true);

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
		$categoryId = 1;
		$moderatorId = 'moderator1';
		$thread = $this->createMockThread($threadId, $categoryId, 'user1', 'Test Thread');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($moderatorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($moderatorId, $categoryId, 'canModerate')
			->willReturn(true);

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
		$categoryId = 1;
		$moderatorId = 'moderator1';
		$thread = $this->createMockThread($threadId, $categoryId, 'user1', 'Test Thread');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($moderatorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($moderatorId, $categoryId, 'canModerate')
			->willReturn(true);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				$this->assertTrue($updatedThread->getIsHidden());
				return $updatedThread;
			});

		$response = $this->controller->update($threadId, null, null, null, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateThreadReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;
		$newTitle = 'New Title';

		$this->userSession->method('getUser')->willReturn(null);

		$this->threadMapper->expects($this->never())
			->method('find');

		$response = $this->controller->update($threadId, $newTitle);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testUpdateThreadReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->update($threadId, 'New Title');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testUpdateThreadStatusReturnsForbiddenWhenUserLacksModeratePermission(): void {
		$threadId = 1;
		$categoryId = 1;
		$userId = 'user1';
		$thread = $this->createMockThread($threadId, $categoryId, $userId, 'Test Thread');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, $categoryId, 'canModerate')
			->willReturn(false);

		$this->threadMapper->expects($this->never())
			->method('update');

		$response = $this->controller->update($threadId, null, true);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertEquals(['error' => 'You do not have permission to modify thread status'], $response->getData());
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

	public function testByAuthorReturnsThreadsSuccessfully(): void {
		$authorId = 'user1';
		$limit = 50;
		$offset = 0;

		$thread1 = $this->createMockThread(1, 1, $authorId, 'Thread 1');
		$thread2 = $this->createMockThread(2, 1, $authorId, 'Thread 2');
		$threads = [$thread1, $thread2];

		$enrichedAuthor = [
			'userId' => $authorId,
			'displayName' => 'Test User',
		];

		$this->threadMapper->expects($this->once())
			->method('findByAuthorId')
			->with($authorId, $limit, $offset)
			->willReturn($threads);

		$this->userService->expects($this->once())
			->method('enrichUserData')
			->with($authorId)
			->willReturn($enrichedAuthor);

		$this->threadEnrichmentService->expects($this->exactly(2))
			->method('enrichThread')
			->willReturnCallback(function ($thread, $author) {
				$data = $thread->jsonSerialize();
				$data['author'] = $author;
				return $data;
			});

		$response = $this->controller->byAuthor($authorId, $limit, $offset);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals(1, $data[0]['id']);
		$this->assertEquals(2, $data[1]['id']);
		$this->assertEquals($enrichedAuthor, $data[0]['author']);
	}

	public function testSetLockedUpdatesThreadSuccessfully(): void {
		$threadId = 1;
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Test Thread');
		$thread->setIsLocked(false);

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

		$this->threadEnrichmentService->expects($this->once())
			->method('enrichThread')
			->willReturnCallback(function ($thread) {
				return $thread->jsonSerialize();
			});

		$response = $this->controller->setLocked($threadId, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['id']);
	}

	public function testSetLockedReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->setLocked($threadId, true);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testSetPinnedUpdatesThreadSuccessfully(): void {
		$threadId = 1;
		$thread = $this->createMockThread($threadId, 1, 'user1', 'Test Thread');
		$thread->setIsPinned(false);

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

		$this->threadEnrichmentService->expects($this->once())
			->method('enrichThread')
			->willReturnCallback(function ($thread) {
				return $thread->jsonSerialize();
			});

		$response = $this->controller->setPinned($threadId, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['id']);
	}

	public function testSetPinnedReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->setPinned($threadId, true);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testMoveThreadSuccessfully(): void {
		$threadId = 1;
		$oldCategoryId = 1;
		$newCategoryId = 2;
		$moderatorId = 'moderator1';

		$thread = $this->createMockThread($threadId, $oldCategoryId, 'user1', 'Test Thread');
		$thread->setPostCount(5);

		$oldCategory = new Category();
		$oldCategory->setId($oldCategoryId);
		$oldCategory->setThreadCount(10);
		$oldCategory->setPostCount(50);

		$newCategory = new Category();
		$newCategory->setId($newCategoryId);
		$newCategory->setThreadCount(3);
		$newCategory->setPostCount(15);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($moderatorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->categoryMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(function ($id) use ($oldCategory, $newCategory, $oldCategoryId, $newCategoryId) {
				if ($id === $newCategoryId) {
					return $newCategory;
				} elseif ($id === $oldCategoryId) {
					return $oldCategory;
				}
				throw new DoesNotExistException('Category not found');
			});

		// User has moderation permission on target category
		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($moderatorId, $newCategoryId, 'canModerate')
			->willReturn(true);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) use ($newCategoryId) {
				$this->assertEquals($newCategoryId, $updatedThread->getCategoryId());
				return $updatedThread;
			});

		// Verify category counts are updated
		$this->categoryMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function ($category) use ($oldCategoryId, $newCategoryId) {
				if ($category->getId() === $oldCategoryId) {
					// Old category should have decremented counts
					$this->assertEquals(9, $category->getThreadCount());
					$this->assertEquals(45, $category->getPostCount());
				} elseif ($category->getId() === $newCategoryId) {
					// New category should have incremented counts
					$this->assertEquals(4, $category->getThreadCount());
					$this->assertEquals(20, $category->getPostCount());
				}
				return $category;
			});

		$response = $this->controller->move($threadId, $newCategoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['id']);
	}

	public function testMoveThreadReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;
		$newCategoryId = 2;

		$this->userSession->method('getUser')->willReturn(null);

		// Thread should not be queried if user is not authenticated
		$this->threadMapper->expects($this->never())
			->method('find');

		$this->threadMapper->expects($this->never())
			->method('update');

		$response = $this->controller->move($threadId, $newCategoryId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testMoveThreadReturnsNotFoundWhenThreadDoesNotExist(): void {
		$threadId = 999;
		$newCategoryId = 2;

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('moderator1');
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->move($threadId, $newCategoryId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Thread not found'], $response->getData());
	}

	public function testMoveThreadReturnsNotFoundWhenTargetCategoryDoesNotExist(): void {
		$threadId = 1;
		$oldCategoryId = 1;
		$newCategoryId = 999;

		$thread = $this->createMockThread($threadId, $oldCategoryId, 'user1', 'Test Thread');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('moderator1');
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($newCategoryId)
			->willThrowException(new DoesNotExistException('Category not found'));

		$this->threadMapper->expects($this->never())
			->method('update');

		$response = $this->controller->move($threadId, $newCategoryId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Target category not found'], $response->getData());
	}

	public function testMoveThreadReturnsForbiddenWhenUserLacksPermissionOnTargetCategory(): void {
		$threadId = 1;
		$oldCategoryId = 1;
		$newCategoryId = 2;
		$userId = 'user1';

		$thread = $this->createMockThread($threadId, $oldCategoryId, $userId, 'Test Thread');

		$newCategory = new Category();
		$newCategory->setId($newCategoryId);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->with($newCategoryId)
			->willReturn($newCategory);

		// User does not have moderation permission on target category
		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, $newCategoryId, 'canModerate')
			->willReturn(false);

		$this->threadMapper->expects($this->never())
			->method('update');

		$response = $this->controller->move($threadId, $newCategoryId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertEquals(
			['error' => 'You do not have permission to move threads to this category'],
			$response->getData()
		);
	}

	public function testMoveThreadUpdatesCategoryCounts(): void {
		$threadId = 1;
		$oldCategoryId = 1;
		$newCategoryId = 2;
		$moderatorId = 'moderator1';
		$threadPostCount = 7;

		$thread = $this->createMockThread($threadId, $oldCategoryId, 'user1', 'Test Thread');
		$thread->setPostCount($threadPostCount);

		$oldCategory = new Category();
		$oldCategory->setId($oldCategoryId);
		$oldCategory->setThreadCount(15);
		$oldCategory->setPostCount(100);

		$newCategory = new Category();
		$newCategory->setId($newCategoryId);
		$newCategory->setThreadCount(5);
		$newCategory->setPostCount(30);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($moderatorId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->threadMapper->expects($this->once())
			->method('find')
			->willReturn($thread);

		$this->categoryMapper->expects($this->exactly(2))
			->method('find')
			->willReturnCallback(function ($id) use ($oldCategory, $newCategory, $oldCategoryId, $newCategoryId) {
				if ($id === $newCategoryId) {
					return $newCategory;
				} elseif ($id === $oldCategoryId) {
					return $oldCategory;
				}
				throw new DoesNotExistException('Category not found');
			});

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->willReturn(true);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturn($thread);

		$oldCategoryUpdated = false;
		$newCategoryUpdated = false;

		$this->categoryMapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function ($category) use (
				$oldCategoryId,
				$newCategoryId,
				$threadPostCount,
				&$oldCategoryUpdated,
				&$newCategoryUpdated
			) {
				if ($category->getId() === $oldCategoryId) {
					// Old category: thread count -1, post count -7
					$this->assertEquals(14, $category->getThreadCount());
					$this->assertEquals(93, $category->getPostCount());
					$oldCategoryUpdated = true;
				} elseif ($category->getId() === $newCategoryId) {
					// New category: thread count +1, post count +7
					$this->assertEquals(6, $category->getThreadCount());
					$this->assertEquals(37, $category->getPostCount());
					$newCategoryUpdated = true;
				}
				return $category;
			});

		$response = $this->controller->move($threadId, $newCategoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($oldCategoryUpdated, 'Old category should have been updated');
		$this->assertTrue($newCategoryUpdated, 'New category should have been updated');
	}

	public function testByCategoryPaginatedReturnsThreadsWithPaginationMetadata(): void {
		$categoryId = 1;
		$page = 1;
		$perPage = 20;

		$thread1 = $this->createMockThread(1, $categoryId, 'user1', 'Test Thread 1');
		$thread2 = $this->createMockThread(2, $categoryId, 'user2', 'Test Thread 2');
		$threads = [$thread1, $thread2];

		$this->threadMapper->expects($this->once())
			->method('countByCategoryId')
			->with($categoryId)
			->willReturn(2);

		$this->threadMapper->expects($this->once())
			->method('findByCategoryId')
			->with($categoryId, $perPage, 0)
			->willReturn($threads);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->willReturn([
				'user1' => ['userId' => 'user1', 'displayName' => 'User 1'],
				'user2' => ['userId' => 'user2', 'displayName' => 'User 2'],
			]);

		$response = $this->controller->byCategoryPaginated($categoryId, $page, $perPage);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('threads', $data);
		$this->assertArrayHasKey('pagination', $data);
		$this->assertCount(2, $data['threads']);
		$this->assertEquals(1, $data['pagination']['page']);
		$this->assertEquals(20, $data['pagination']['perPage']);
		$this->assertEquals(2, $data['pagination']['total']);
		$this->assertEquals(1, $data['pagination']['totalPages']);
	}

	public function testByCategoryPaginatedCalculatesTotalPagesCorrectly(): void {
		$categoryId = 1;
		$page = 1;
		$perPage = 20;

		// 45 threads = 3 pages (20 + 20 + 5)
		$this->threadMapper->expects($this->once())
			->method('countByCategoryId')
			->with($categoryId)
			->willReturn(45);

		$this->threadMapper->expects($this->once())
			->method('findByCategoryId')
			->with($categoryId, $perPage, 0)
			->willReturn([]);

		$this->userService->method('enrichMultipleUsers')->willReturn([]);

		$response = $this->controller->byCategoryPaginated($categoryId, $page, $perPage);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(3, $data['pagination']['totalPages']);
		$this->assertEquals(45, $data['pagination']['total']);
	}

	public function testByCategoryPaginatedFetchesCorrectOffset(): void {
		$categoryId = 1;
		$page = 3;
		$perPage = 20;

		$this->threadMapper->expects($this->once())
			->method('countByCategoryId')
			->with($categoryId)
			->willReturn(60);

		// Page 3 should have offset 40 (2 * 20)
		$this->threadMapper->expects($this->once())
			->method('findByCategoryId')
			->with($categoryId, $perPage, 40)
			->willReturn([]);

		$this->userService->method('enrichMultipleUsers')->willReturn([]);

		$response = $this->controller->byCategoryPaginated($categoryId, $page, $perPage);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(3, $data['pagination']['page']);
	}

	public function testByCategoryPaginatedClampsPageToValidRange(): void {
		$categoryId = 1;
		$perPage = 20;

		// Only 30 threads = 2 pages
		$this->threadMapper->expects($this->once())
			->method('countByCategoryId')
			->with($categoryId)
			->willReturn(30);

		// Request page 5 but should be clamped to page 2 (offset 20)
		$this->threadMapper->expects($this->once())
			->method('findByCategoryId')
			->with($categoryId, $perPage, 20)
			->willReturn([]);

		$this->userService->method('enrichMultipleUsers')->willReturn([]);

		$response = $this->controller->byCategoryPaginated($categoryId, 5, $perPage);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(2, $data['pagination']['page']); // Clamped to max page
		$this->assertEquals(2, $data['pagination']['totalPages']);
	}

	public function testByCategoryPaginatedHandlesEmptyCategory(): void {
		$categoryId = 1;
		$page = 1;
		$perPage = 20;

		$this->threadMapper->expects($this->once())
			->method('countByCategoryId')
			->with($categoryId)
			->willReturn(0);

		$this->threadMapper->expects($this->once())
			->method('findByCategoryId')
			->with($categoryId, $perPage, 0)
			->willReturn([]);

		$this->userService->method('enrichMultipleUsers')->willReturn([]);

		$response = $this->controller->byCategoryPaginated($categoryId, $page, $perPage);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(0, $data['threads']);
		$this->assertEquals(1, $data['pagination']['page']);
		$this->assertEquals(1, $data['pagination']['totalPages']);
		$this->assertEquals(0, $data['pagination']['total']);
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
