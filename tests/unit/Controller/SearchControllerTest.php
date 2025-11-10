<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\SearchController;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\SearchService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SearchControllerTest extends TestCase {
	private SearchController $controller;
	private SearchService $searchService;
	private ThreadMapper $threadMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->searchService = $this->createMock(SearchService::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new SearchController(
			Application::APP_ID,
			$this->request,
			$this->searchService,
			$this->threadMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testIndexReturnsErrorWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')
			->willReturn(null);

		$response = $this->controller->index('test query');

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('User not authenticated', $data['error']);
	}

	public function testIndexReturnsErrorWhenQueryIsEmpty(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$response = $this->controller->index('');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Search query is required', $data['error']);
	}

	public function testIndexReturnsErrorWhenQueryIsWhitespace(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$response = $this->controller->index('   ');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Search query is required', $data['error']);
	}

	public function testIndexReturnsErrorWhenNoSearchScopeSelected(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$response = $this->controller->index('test query', false, false);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('At least one search scope must be selected (threads or posts)', $data['error']);
	}

	public function testIndexReturnsSearchResults(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$thread1 = $this->createMockThread(1, 'Test Thread', 'test-thread', 1);
		$thread2 = $this->createMockThread(2, 'Another Thread', 'another-thread', 1);

		$post1 = $this->createMockPost(1, 1, 'user1', 'Test post content');
		$post2 = $this->createMockPost(2, 2, 'user2', 'Another post content');

		$searchResults = [
			'threads' => [$thread1, $thread2],
			'posts' => [$post1, $post2],
			'threadCount' => 2,
			'postCount' => 2,
		];

		$this->searchService->expects($this->once())
			->method('search')
			->with('test query', 'user1', true, true, null, 50, 0)
			->willReturn($searchResults);

		// Mock thread mapper for enriching posts
		$this->threadMapper->expects($this->exactly(2))
			->method('find')
			->willReturnMap([
				[1, $thread1],
				[2, $thread2],
			]);

		$response = $this->controller->index('test query');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('threads', $data);
		$this->assertArrayHasKey('posts', $data);
		$this->assertArrayHasKey('threadCount', $data);
		$this->assertArrayHasKey('postCount', $data);
		$this->assertArrayHasKey('query', $data);
		$this->assertEquals('test query', $data['query']);
		$this->assertEquals(2, $data['threadCount']);
		$this->assertEquals(2, $data['postCount']);
		$this->assertCount(2, $data['threads']);
		$this->assertCount(2, $data['posts']);
	}

	public function testIndexWithThreadsOnlySearch(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$thread1 = $this->createMockThread(1, 'Test Thread', 'test-thread', 1);

		$searchResults = [
			'threads' => [$thread1],
			'posts' => [],
			'threadCount' => 1,
			'postCount' => 0,
		];

		$this->searchService->expects($this->once())
			->method('search')
			->with('test query', 'user1', true, false, null, 50, 0)
			->willReturn($searchResults);

		$response = $this->controller->index('test query', true, false);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['threadCount']);
		$this->assertEquals(0, $data['postCount']);
		$this->assertCount(1, $data['threads']);
		$this->assertCount(0, $data['posts']);
	}

	public function testIndexWithPostsOnlySearch(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$post1 = $this->createMockPost(1, 1, 'user1', 'Test post content');
		$thread1 = $this->createMockThread(1, 'Test Thread', 'test-thread', 1);

		$searchResults = [
			'threads' => [],
			'posts' => [$post1],
			'threadCount' => 0,
			'postCount' => 1,
		];

		$this->searchService->expects($this->once())
			->method('search')
			->with('test query', 'user1', false, true, null, 50, 0)
			->willReturn($searchResults);

		// Mock thread mapper for enriching posts
		$this->threadMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($thread1);

		$response = $this->controller->index('test query', false, true);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(0, $data['threadCount']);
		$this->assertEquals(1, $data['postCount']);
		$this->assertCount(0, $data['threads']);
		$this->assertCount(1, $data['posts']);
	}

	public function testIndexWithCategoryFilter(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$thread1 = $this->createMockThread(1, 'Test Thread', 'test-thread', 1);

		$searchResults = [
			'threads' => [$thread1],
			'posts' => [],
			'threadCount' => 1,
			'postCount' => 0,
		];

		$this->searchService->expects($this->once())
			->method('search')
			->with('test query', 'user1', true, true, 1, 50, 0)
			->willReturn($searchResults);

		$response = $this->controller->index('test query', true, true, 1);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['threadCount']);
	}

	public function testIndexWithCustomLimitAndOffset(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$searchResults = [
			'threads' => [],
			'posts' => [],
			'threadCount' => 0,
			'postCount' => 0,
		];

		$this->searchService->expects($this->once())
			->method('search')
			->with('test query', 'user1', true, true, null, 25, 10)
			->willReturn($searchResults);

		$response = $this->controller->index('test query', true, true, null, 25, 10);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testIndexHandlesDeletedThread(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$post1 = $this->createMockPost(1, 1, 'user1', 'Test post content');

		$searchResults = [
			'threads' => [],
			'posts' => [$post1],
			'threadCount' => 0,
			'postCount' => 1,
		];

		$this->searchService->expects($this->once())
			->method('search')
			->willReturn($searchResults);

		// Thread not found (deleted)
		$this->threadMapper->expects($this->once())
			->method('find')
			->with(1)
			->willThrowException(new DoesNotExistException('Thread not found'));

		$response = $this->controller->index('test query');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(1, $data['posts']);
		// Check that thread context is null
		$this->assertNull($data['posts'][0]['threadTitle']);
		$this->assertNull($data['posts'][0]['threadSlug']);
	}

	public function testIndexHandlesSearchServiceException(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$this->searchService->expects($this->once())
			->method('search')
			->willThrowException(new \Exception('Database error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->controller->index('test query');

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertEquals('Failed to perform search', $data['error']);
	}

	private function createMockThread(int $id, string $title, string $slug, int $categoryId): Thread {
		$thread = new Thread();
		$thread->setId($id);
		$thread->setTitle($title);
		$thread->setSlug($slug);
		$thread->setCategoryId($categoryId);
		$thread->setAuthorId('user1');
		$thread->setCreatedAt(time());
		$thread->setUpdatedAt(time());
		$thread->setIsPinned(false);
		$thread->setIsLocked(false);
		$thread->setIsHidden(false);
		$thread->setPostCount(1);
		$thread->setViewCount(0);
		return $thread;
	}

	private function createMockPost(int $id, int $threadId, string $authorId, string $content): Post {
		$post = new Post();
		$post->setId($id);
		$post->setThreadId($threadId);
		$post->setAuthorId($authorId);
		$post->setContent($content);
		$post->setCreatedAt(time());
		$post->setUpdatedAt(time());
		$post->setIsFirstPost(false);
		return $post;
	}
}
