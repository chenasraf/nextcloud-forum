<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\PostController;
use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Reaction;
use OCA\Forum\Db\ReactionMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\UserStats;
use OCA\Forum\Db\UserStatsMapper;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\NotificationService;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\PostEnrichmentService;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostControllerTest extends TestCase {
	private PostController $controller;
	private PostMapper $postMapper;
	private ThreadMapper $threadMapper;
	private CategoryMapper $categoryMapper;
	private UserStatsMapper $userStatsMapper;
	private ReactionMapper $reactionMapper;
	private BBCodeService $bbCodeService;
	private BBCodeMapper $bbCodeMapper;
	private PermissionService $permissionService;
	private ReadMarkerMapper $readMarkerMapper;
	private NotificationService $notificationService;
	private PostEnrichmentService $postEnrichmentService;
	private UserService $userService;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->userStatsMapper = $this->createMock(UserStatsMapper::class);
		$this->reactionMapper = $this->createMock(ReactionMapper::class);
		$this->bbCodeService = $this->createMock(BBCodeService::class);
		$this->bbCodeMapper = $this->createMock(BBCodeMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->readMarkerMapper = $this->createMock(ReadMarkerMapper::class);
		$this->notificationService = $this->createMock(NotificationService::class);
		$this->postEnrichmentService = $this->createMock(PostEnrichmentService::class);
		$this->userService = $this->createMock(UserService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		// Mock post enrichment service to return serialized post with mock data
		$this->postEnrichmentService->method('enrichPost')
			->willReturnCallback(function ($post) {
				$data = is_array($post) ? $post : $post->jsonSerialize();
				$data['author'] = ['userId' => $data['authorId'], 'displayName' => 'Test User'];
				$data['reactions'] = [];
				return $data;
			});

		$this->controller = new PostController(
			Application::APP_ID,
			$this->request,
			$this->postMapper,
			$this->threadMapper,
			$this->categoryMapper,
			$this->userStatsMapper,
			$this->reactionMapper,
			$this->bbCodeService,
			$this->bbCodeMapper,
			$this->permissionService,
			$this->readMarkerMapper,
			$this->notificationService,
			$this->postEnrichmentService,
			$this->userService,
			$this->userSession,
			$this->logger
		);
	}

	public function testByThreadReturnsPostsSuccessfully(): void {
		$threadId = 1;
		$limit = 50;
		$offset = 0;

		// Create mock posts
		$post1 = $this->createMockPost(1, $threadId, 'user1', 'Test content 1');
		$post2 = $this->createMockPost(2, $threadId, 'user2', 'Test content 2');
		$posts = [$post1, $post2];

		// Create mock BBCode
		$bbcode = new BBCode();
		$bbcode->setId(1);
		$bbcode->setTag('b');
		$bbcode->setReplacement('<strong>{content}</strong>');
		$bbcode->setEnabled(true);

		// Create mock reactions
		$reaction1 = $this->createMockReaction(1, 1, 'user1', '👍');
		$reaction2 = $this->createMockReaction(2, 1, 'user2', '👍');

		// Mock user session
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		// Set up expectations
		$this->postMapper->expects($this->once())
			->method('findByThreadId')
			->with($threadId, $limit, $offset)
			->willReturn($posts);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllEnabled')
			->willReturn([$bbcode]);

		$this->reactionMapper->expects($this->once())
			->method('findByPostIds')
			->with([1, 2])
			->willReturn([$reaction1, $reaction2]);

		// Mock userService to return enriched user data
		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with(['user1', 'user2'])
			->willReturn([
				'user1' => ['userId' => 'user1', 'displayName' => 'User 1', 'roles' => []],
				'user2' => ['userId' => 'user2', 'displayName' => 'User 2', 'roles' => []],
			]);

		// Execute
		$response = $this->controller->byThread($threadId, $limit, $offset);

		// Assert
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals(1, $data[0]['id']);
		$this->assertEquals(2, $data[1]['id']);
		$this->assertArrayHasKey('reactions', $data[0]);
		$this->assertArrayHasKey('reactions', $data[1]);
	}

	public function testByThreadHandlesEmptyPosts(): void {
		$threadId = 1;

		$this->postMapper->expects($this->once())
			->method('findByThreadId')
			->willReturn([]);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllEnabled')
			->willReturn([]);

		$this->reactionMapper->expects($this->once())
			->method('findByPostIds')
			->with([])
			->willReturn([]);

		$response = $this->controller->byThread($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertIsArray($response->getData());
		$this->assertCount(0, $response->getData());
	}

	public function testShowReturnsPostSuccessfully(): void {
		$postId = 1;
		$post = $this->createMockPost($postId, 1, 'user1', 'Test content');

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$response = $this->controller->show($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($postId, $data['id']);
		$this->assertEquals('user1', $data['authorId']);
	}

	public function testShowReturnsNotFoundWhenPostDoesNotExist(): void {
		$postId = 999;

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willThrowException(new DoesNotExistException('Post not found'));

		$response = $this->controller->show($postId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Post not found'], $response->getData());
	}

	public function testCreatePostSuccessfully(): void {
		$threadId = 1;
		$content = 'New post content';
		$userId = 'user1';

		// Mock user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// Mock forum user
		$forumUser = new UserStats();
		$forumUser->setId(1);
		$forumUser->setUserId($userId);
		$forumUser->setPostCount(0);
		$forumUser->setCreatedAt(time());
		$forumUser->setUpdatedAt(time());

		// Mock thread
		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setCategoryId(1);
		$thread->setPostCount(1);
		$thread->setCreatedAt(time());
		$thread->setUpdatedAt(time());

		// Mock category
		$category = new Category();
		$category->setId(1);
		$category->setPostCount(1);

		// Mock created post
		$createdPost = $this->createMockPost(1, $threadId, $userId, $content);

		$this->postMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($post) use ($createdPost) {
				return $createdPost;
			});

		// Mock readMarkerMapper
		$this->readMarkerMapper->expects($this->once())
			->method('createOrUpdate')
			->with($userId, $threadId, 1);

		// Mock thread update
		$this->threadMapper->expects($this->once())
			->method('find')
			->with($threadId)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturn($thread);

		// Mock category update
		$this->categoryMapper->expects($this->once())
			->method('find')
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturn($category);

		// Mock user stats increment (void methods, no return value expectations)
		$this->userStatsMapper->expects($this->once())
			->method('incrementPostCount')
			->with($userId);

		// Mock notification service
		$this->notificationService->expects($this->once())
			->method('notifyThreadSubscribers')
			->with($threadId, 1, $userId);

		$response = $this->controller->create($threadId, $content);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['id']);
		$this->assertEquals($threadId, $data['threadId']);
		$this->assertEquals($userId, $data['authorId']);
	}

	public function testCreatePostReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;
		$content = 'New post content';

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create($threadId, $content);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testUpdatePostSuccessfullyAsAuthor(): void {
		$postId = 1;
		$userId = 'user1';
		$newContent = 'Updated content';
		$post = $this->createMockPost($postId, 1, $userId, 'Original content');

		// Mock user (author)
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// Mock permission service
		$this->permissionService->expects($this->once())
			->method('getCategoryIdFromPost')
			->with($postId)
			->willReturn(1);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, 1, 'canModerate')
			->willReturn(false); // User is not a moderator, but is the author

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$this->postMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedPost) use ($newContent) {
				$this->assertEquals($newContent, $updatedPost->getContent());
				$this->assertTrue($updatedPost->getIsEdited());
				$this->assertNotNull($updatedPost->getEditedAt());
				return $updatedPost;
			});

		$response = $this->controller->update($postId, $newContent);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($postId, $data['id']);
	}

	public function testUpdatePostSuccessfullyAsModerator(): void {
		$postId = 1;
		$userId = 'moderator1';
		$postAuthorId = 'user1';
		$newContent = 'Updated content';
		$post = $this->createMockPost($postId, 1, $postAuthorId, 'Original content');

		// Mock user (moderator, not author)
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// Mock permission service
		$this->permissionService->expects($this->once())
			->method('getCategoryIdFromPost')
			->with($postId)
			->willReturn(1);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, 1, 'canModerate')
			->willReturn(true); // User is a moderator

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$this->postMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedPost) use ($newContent) {
				$this->assertEquals($newContent, $updatedPost->getContent());
				$this->assertTrue($updatedPost->getIsEdited());
				$this->assertNotNull($updatedPost->getEditedAt());
				return $updatedPost;
			});

		$response = $this->controller->update($postId, $newContent);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($postId, $data['id']);
	}

	public function testUpdatePostReturnsForbiddenWhenNotAuthorOrModerator(): void {
		$postId = 1;
		$userId = 'user2';
		$postAuthorId = 'user1';
		$newContent = 'Updated content';
		$post = $this->createMockPost($postId, 1, $postAuthorId, 'Original content');

		// Mock user (not author, not moderator)
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// Mock permission service
		$this->permissionService->expects($this->once())
			->method('getCategoryIdFromPost')
			->with($postId)
			->willReturn(1);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, 1, 'canModerate')
			->willReturn(false); // User is neither author nor moderator

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$response = $this->controller->update($postId, $newContent);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertEquals(['error' => 'Insufficient permissions to edit this post'], $response->getData());
	}

	public function testUpdatePostReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$postId = 1;
		$newContent = 'Updated content';

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->update($postId, $newContent);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testUpdatePostReturnsNotFoundWhenPostDoesNotExist(): void {
		$postId = 999;
		$userId = 'user1';
		$newContent = 'Updated content';

		// Mock user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willThrowException(new DoesNotExistException('Post not found'));

		$response = $this->controller->update($postId, $newContent);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Post not found'], $response->getData());
	}

	public function testDestroyPostSuccessfully(): void {
		$postId = 1;
		$userId = 'user1';
		$post = $this->createMockPost($postId, 1, $userId, 'Test content');

		// Mock user session
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// Mock permission service
		$this->permissionService->expects($this->once())
			->method('getCategoryIdFromPost')
			->with($postId)
			->willReturn(1);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with($userId, 1, 'canModerate')
			->willReturn(false); // User is not moderator but is author

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$this->postMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedPost) {
				$this->assertNotNull($updatedPost->getDeletedAt());
				return $updatedPost;
			});

		$response = $this->controller->destroy($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['success' => true], $response->getData());
	}

	public function testDestroyPostReturnsNotFoundWhenPostDoesNotExist(): void {
		$postId = 999;
		$userId = 'user1';

		// Mock user session
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willThrowException(new DoesNotExistException('Post not found'));

		$response = $this->controller->destroy($postId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Post not found'], $response->getData());
	}

	private function createMockPost(int $id, int $threadId, string $authorId, string $content): Post {
		$post = new Post();
		$post->setId($id);
		$post->setThreadId($threadId);
		$post->setAuthorId($authorId);
		$post->setContent($content);
		$post->setSlug("post-$id");
		$post->setIsEdited(false);
		$post->setIsFirstPost(false);
		$post->setCreatedAt(time());
		$post->setUpdatedAt(time());
		return $post;
	}

	private function createMockReaction(int $id, int $postId, string $userId, string $reactionType): Reaction {
		$reaction = new Reaction();
		$reaction->setId($id);
		$reaction->setPostId($postId);
		$reaction->setUserId($userId);
		$reaction->setReactionType($reactionType);
		$reaction->setCreatedAt(time());
		return $reaction;
	}

	public function testCreatePostIncrementsThreadPostCount(): void {
		$threadId = 1;
		$content = 'New reply post content';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setCategoryId(1);
		$thread->setPostCount(5); // Thread has 5 replies

		$category = new Category();
		$category->setId(1);
		$category->setPostCount(10); // Category has 10 total replies

		$createdPost = $this->createMockPost(1, $threadId, $userId, $content);

		$this->postMapper->expects($this->once())
			->method('insert')
			->willReturn($createdPost);

		$this->readMarkerMapper->method('createOrUpdate');

		$this->threadMapper->expects($this->once())
			->method('find')
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				// Thread post count should be incremented from 5 to 6
				$this->assertEquals(6, $updatedThread->getPostCount());
				return $updatedThread;
			});

		$this->categoryMapper->expects($this->once())
			->method('find')
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedCategory) {
				// Category post count should be incremented from 10 to 11
				$this->assertEquals(11, $updatedCategory->getPostCount());
				return $updatedCategory;
			});

		$this->userStatsMapper->expects($this->once())
			->method('incrementPostCount')
			->with($userId);

		$this->notificationService->method('notifyThreadSubscribers');

		$response = $this->controller->create($threadId, $content);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testDestroyPostDecrementsThreadPostCount(): void {
		$postId = 1;
		$userId = 'user1';
		$post = $this->createMockPost($postId, 1, $userId, 'Test content');
		$post->setIsFirstPost(false); // Regular reply post

		$thread = new Thread();
		$thread->setId(1);
		$thread->setCategoryId(1);
		$thread->setPostCount(5); // Thread has 5 replies
		$thread->setLastPostId($postId); // This post is the last post

		$category = new Category();
		$category->setId(1);
		$category->setPostCount(10); // Category has 10 total replies

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->permissionService->method('getCategoryIdFromPost')->willReturn(1);
		$this->permissionService->method('hasCategoryPermission')->willReturn(false);

		$this->postMapper->expects($this->once())
			->method('find')
			->willReturn($post);

		$this->postMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedPost) {
				$this->assertNotNull($updatedPost->getDeletedAt());
				return $updatedPost;
			});

		$this->threadMapper->expects($this->once())
			->method('find')
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				// Thread post count should be decremented from 5 to 4
				$this->assertEquals(4, $updatedThread->getPostCount());
				return $updatedThread;
			});

		// Mock finding the last post (not the deleted one)
		$lastPost = $this->createMockPost(2, 1, $userId, 'Last post');
		$this->postMapper->expects($this->once())
			->method('findLatestByThreadId')
			->with(1, $postId)
			->willReturn($lastPost);

		$this->categoryMapper->expects($this->once())
			->method('find')
			->willReturn($category);

		$this->categoryMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedCategory) {
				// Category post count should be decremented from 10 to 9
				$this->assertEquals(9, $updatedCategory->getPostCount());
				return $updatedCategory;
			});

		$this->userStatsMapper->expects($this->once())
			->method('decrementPostCount')
			->with($userId);

		$response = $this->controller->destroy($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyFirstPostDecrementsThreadCount(): void {
		$postId = 1;
		$userId = 'user1';
		$post = $this->createMockPost($postId, 1, $userId, 'First post content');
		$post->setIsFirstPost(true); // First post

		$thread = new Thread();
		$thread->setId(1);
		$thread->setCategoryId(1);
		$thread->setPostCount(3); // Thread has 3 replies
		$thread->setLastPostId($postId); // This post is the last post

		$category = new Category();
		$category->setId(1);
		$category->setPostCount(10); // Category has 10 total replies

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->permissionService->method('getCategoryIdFromPost')->willReturn(1);
		$this->permissionService->method('hasCategoryPermission')->willReturn(false);

		$this->postMapper->expects($this->once())
			->method('find')
			->willReturn($post);

		$this->postMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedPost) {
				$this->assertNotNull($updatedPost->getDeletedAt());
				return $updatedPost;
			});

		$this->threadMapper->expects($this->once())
			->method('find')
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedThread) {
				// Thread post count should stay at 3 (first posts don't count)
				$this->assertEquals(3, $updatedThread->getPostCount());
				return $updatedThread;
			});

		$lastPost = $this->createMockPost(2, 1, $userId, 'Last post');
		$this->postMapper->expects($this->once())
			->method('findLatestByThreadId')
			->with(1, $postId)
			->willReturn($lastPost);

		// Category mapper should not be called for first post deletion
		$this->categoryMapper->expects($this->never())
			->method('find');

		$this->categoryMapper->expects($this->never())
			->method('update');

		// First post deletion should decrement thread count, not post count
		$this->userStatsMapper->expects($this->once())
			->method('decrementThreadCount')
			->with($userId);

		$this->userStatsMapper->expects($this->never())
			->method('decrementPostCount');

		$response = $this->controller->destroy($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}
}
