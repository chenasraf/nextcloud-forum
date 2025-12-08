<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\BookmarkController;
use OCA\Forum\Db\Bookmark;
use OCA\Forum\Db\BookmarkMapper;
use OCA\Forum\Db\ReadMarker;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\ThreadEnrichmentService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BookmarkControllerTest extends TestCase {
	private BookmarkController $controller;
	private BookmarkMapper $bookmarkMapper;
	private ThreadMapper $threadMapper;
	private ReadMarkerMapper $readMarkerMapper;
	private ThreadEnrichmentService $threadEnrichmentService;
	private UserService $userService;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->bookmarkMapper = $this->createMock(BookmarkMapper::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->readMarkerMapper = $this->createMock(ReadMarkerMapper::class);
		$this->threadEnrichmentService = $this->createMock(ThreadEnrichmentService::class);
		$this->userService = $this->createMock(UserService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new BookmarkController(
			Application::APP_ID,
			$this->request,
			$this->bookmarkMapper,
			$this->threadMapper,
			$this->readMarkerMapper,
			$this->threadEnrichmentService,
			$this->userService,
			$this->userSession,
			$this->logger
		);
	}

	public function testBookmarkThreadSuccessfully(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$bookmark = $this->createMockBookmark(1, $userId, Bookmark::ENTITY_TYPE_THREAD, $threadId);

		$this->bookmarkMapper->expects($this->once())
			->method('bookmarkThread')
			->with($userId, $threadId)
			->willReturn($bookmark);

		$response = $this->controller->bookmarkThread($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('bookmark', $data);
		$this->assertEquals($userId, $data['bookmark']['userId']);
		$this->assertEquals(Bookmark::ENTITY_TYPE_THREAD, $data['bookmark']['entityType']);
		$this->assertEquals($threadId, $data['bookmark']['entityId']);
	}

	public function testBookmarkThreadReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->bookmarkThread($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testUnbookmarkThreadSuccessfully(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->bookmarkMapper->expects($this->once())
			->method('unbookmarkThread')
			->with($userId, $threadId);

		$response = $this->controller->unbookmarkThread($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUnbookmarkThreadReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->unbookmarkThread($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testIsThreadBookmarkedReturnsTrue(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->bookmarkMapper->expects($this->once())
			->method('isThreadBookmarked')
			->with($userId, $threadId)
			->willReturn(true);

		$response = $this->controller->isThreadBookmarked($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['isBookmarked']);
	}

	public function testIsThreadBookmarkedReturnsFalse(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->bookmarkMapper->expects($this->once())
			->method('isThreadBookmarked')
			->with($userId, $threadId)
			->willReturn(false);

		$response = $this->controller->isThreadBookmarked($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['isBookmarked']);
	}

	public function testIsThreadBookmarkedReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->isThreadBookmarked($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testIndexReturnsBookmarkedThreadsSuccessfully(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$bookmark1 = $this->createMockBookmark(1, $userId, Bookmark::ENTITY_TYPE_THREAD, 1);
		$bookmark2 = $this->createMockBookmark(2, $userId, Bookmark::ENTITY_TYPE_THREAD, 2);
		$bookmarks = [$bookmark1, $bookmark2];

		$thread1 = $this->createMockThread(1, 'Thread 1', 'thread-1', 'author1', 1);
		$thread2 = $this->createMockThread(2, 'Thread 2', 'thread-2', 'author2', 1);
		$threads = [$thread1, $thread2];

		$this->bookmarkMapper->expects($this->once())
			->method('countThreadBookmarksByUserId')
			->with($userId)
			->willReturn(2);

		$this->bookmarkMapper->expects($this->once())
			->method('findThreadBookmarksByUserId')
			->with($userId, 20, 0)
			->willReturn($bookmarks);

		$this->threadMapper->expects($this->once())
			->method('findByIds')
			->with([1, 2])
			->willReturn($threads);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->willReturn([
				'author1' => ['userId' => 'author1', 'displayName' => 'Author 1'],
				'author2' => ['userId' => 'author2', 'displayName' => 'Author 2'],
			]);

		$this->threadEnrichmentService->expects($this->exactly(2))
			->method('enrichThread')
			->willReturnCallback(function ($thread, $author) {
				$data = $thread->jsonSerialize();
				$data['author'] = $author;
				return $data;
			});

		$this->readMarkerMapper->expects($this->once())
			->method('findByUserAndThreads')
			->with($userId, [1, 2])
			->willReturn([]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('threads', $data);
		$this->assertArrayHasKey('pagination', $data);
		$this->assertArrayHasKey('readMarkers', $data);
		$this->assertCount(2, $data['threads']);
		$this->assertEquals(1, $data['pagination']['page']);
		$this->assertEquals(2, $data['pagination']['total']);
	}

	public function testIndexHandlesEmptyBookmarks(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->bookmarkMapper->expects($this->once())
			->method('countThreadBookmarksByUserId')
			->with($userId)
			->willReturn(0);

		$this->bookmarkMapper->expects($this->once())
			->method('findThreadBookmarksByUserId')
			->with($userId, 20, 0)
			->willReturn([]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('threads', $data);
		$this->assertCount(0, $data['threads']);
		$this->assertEquals(0, $data['pagination']['total']);
		$this->assertEquals(1, $data['pagination']['totalPages']);
	}

	public function testIndexReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testIndexWithPagination(): void {
		$userId = 'user1';
		$page = 2;
		$perPage = 10;

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$bookmark = $this->createMockBookmark(11, $userId, Bookmark::ENTITY_TYPE_THREAD, 11);
		$thread = $this->createMockThread(11, 'Thread 11', 'thread-11', 'author1', 1);

		$this->bookmarkMapper->expects($this->once())
			->method('countThreadBookmarksByUserId')
			->with($userId)
			->willReturn(15);

		$this->bookmarkMapper->expects($this->once())
			->method('findThreadBookmarksByUserId')
			->with($userId, $perPage, 10) // offset = (page - 1) * perPage = 10
			->willReturn([$bookmark]);

		$this->threadMapper->expects($this->once())
			->method('findByIds')
			->with([11])
			->willReturn([$thread]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->willReturn([
				'author1' => ['userId' => 'author1', 'displayName' => 'Author 1'],
			]);

		$this->threadEnrichmentService->expects($this->once())
			->method('enrichThread')
			->willReturnCallback(function ($thread, $author) {
				$data = $thread->jsonSerialize();
				$data['author'] = $author;
				return $data;
			});

		$this->readMarkerMapper->expects($this->once())
			->method('findByUserAndThreads')
			->willReturn([]);

		$response = $this->controller->index($page, $perPage);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(2, $data['pagination']['page']);
		$this->assertEquals(10, $data['pagination']['perPage']);
		$this->assertEquals(15, $data['pagination']['total']);
		$this->assertEquals(2, $data['pagination']['totalPages']);
	}

	public function testIndexIncludesReadMarkers(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$bookmark = $this->createMockBookmark(1, $userId, Bookmark::ENTITY_TYPE_THREAD, 1);
		$thread = $this->createMockThread(1, 'Thread 1', 'thread-1', 'author1', 1);
		$readMarker = $this->createMockReadMarker(1, $userId, 1, 5);

		$this->bookmarkMapper->expects($this->once())
			->method('countThreadBookmarksByUserId')
			->willReturn(1);

		$this->bookmarkMapper->expects($this->once())
			->method('findThreadBookmarksByUserId')
			->willReturn([$bookmark]);

		$this->threadMapper->expects($this->once())
			->method('findByIds')
			->willReturn([$thread]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->willReturn([
				'author1' => ['userId' => 'author1', 'displayName' => 'Author 1'],
			]);

		$this->threadEnrichmentService->expects($this->once())
			->method('enrichThread')
			->willReturnCallback(function ($thread, $author) {
				$data = $thread->jsonSerialize();
				$data['author'] = $author;
				return $data;
			});

		$this->readMarkerMapper->expects($this->once())
			->method('findByUserAndThreads')
			->with($userId, [1])
			->willReturn([$readMarker]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('readMarkers', $data);
		$this->assertArrayHasKey(1, $data['readMarkers']);
		$this->assertEquals(1, $data['readMarkers'][1]['threadId']);
		$this->assertEquals(5, $data['readMarkers'][1]['lastReadPostId']);
	}

	private function createMockBookmark(int $id, string $userId, string $entityType, int $entityId): Bookmark {
		$bookmark = new Bookmark();
		$bookmark->setId($id);
		$bookmark->setUserId($userId);
		$bookmark->setEntityType($entityType);
		$bookmark->setEntityId($entityId);
		$bookmark->setCreatedAt(time());
		return $bookmark;
	}

	private function createMockThread(int $id, string $title, string $slug, string $authorId, int $categoryId): Thread {
		$thread = new Thread();
		$thread->setId($id);
		$thread->setTitle($title);
		$thread->setSlug($slug);
		$thread->setAuthorId($authorId);
		$thread->setCategoryId($categoryId);
		$thread->setViewCount(0);
		$thread->setPostCount(0);
		$thread->setIsLocked(false);
		$thread->setIsPinned(false);
		$thread->setIsHidden(false);
		$thread->setCreatedAt(time());
		$thread->setUpdatedAt(time());
		return $thread;
	}

	private function createMockReadMarker(int $id, string $userId, int $threadId, int $lastReadPostId): ReadMarker {
		$marker = new ReadMarker();
		$marker->setId($id);
		$marker->setUserId($userId);
		$marker->setThreadId($threadId);
		$marker->setLastReadPostId($lastReadPostId);
		$marker->setReadAt(time());
		return $marker;
	}
}
