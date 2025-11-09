<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ForumUserController;
use OCA\Forum\Db\ForumUser;
use OCA\Forum\Db\ForumUserMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ForumUserControllerTest extends TestCase {
	private ForumUserController $controller;
	private ForumUserMapper $forumUserMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->forumUserMapper = $this->createMock(ForumUserMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ForumUserController(
			Application::APP_ID,
			$this->request,
			$this->forumUserMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testIndexReturnsAllForumUsersSuccessfully(): void {
		$user1 = $this->createForumUser(1, 'user1', 10);
		$user2 = $this->createForumUser(2, 'user2', 25);

		$this->forumUserMapper->expects($this->once())
			->method('findAll')
			->willReturn([$user1, $user2]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testShowReturnsForumUserSuccessfully(): void {
		$userId = 1;
		$user = $this->createForumUser($userId, 'user1', 10);

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($userId)
			->willReturn($user);

		$response = $this->controller->show($userId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($userId, $data['id']);
		$this->assertEquals('user1', $data['userId']);
	}

	public function testShowReturnsNotFoundWhenUserDoesNotExist(): void {
		$userId = 999;

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($userId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->show($userId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testByUserIdReturnsForumUserSuccessfully(): void {
		$nextcloudUserId = 'user1';
		$user = $this->createForumUser(1, $nextcloudUserId, 10);

		$this->forumUserMapper->expects($this->once())
			->method('findByUserId')
			->with($nextcloudUserId)
			->willReturn($user);

		$response = $this->controller->byUserId($nextcloudUserId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
	}

	public function testByUserIdReturnsNotFoundWhenUserDoesNotExist(): void {
		$nextcloudUserId = 'non-existent-user';

		$this->forumUserMapper->expects($this->once())
			->method('findByUserId')
			->with($nextcloudUserId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->byUserId($nextcloudUserId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testMeReturnsCurrentUserSuccessfully(): void {
		$nextcloudUserId = 'current-user';
		$forumUser = $this->createForumUser(1, $nextcloudUserId, 15);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($nextcloudUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('findByUserId')
			->with($nextcloudUserId)
			->willReturn($forumUser);

		$response = $this->controller->me();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
	}

	public function testMeReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->me();

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testMeReturnsNotFoundWhenForumUserDoesNotExist(): void {
		$nextcloudUserId = 'user-without-forum-profile';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($nextcloudUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('findByUserId')
			->with($nextcloudUserId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->me();

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testCreateForumUserSuccessfully(): void {
		$nextcloudUserId = 'new-user';
		$createdUser = $this->createForumUser(1, $nextcloudUserId, 0);

		$this->forumUserMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($user) use ($createdUser) {
				return $createdUser;
			});

		$response = $this->controller->create($nextcloudUserId);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['id']);
		$this->assertEquals($nextcloudUserId, $data['userId']);
		$this->assertEquals(0, $data['postCount']);
	}

	public function testUpdateForumUserSuccessfully(): void {
		$userId = 1;
		$newPostCount = 50;
		$user = $this->createForumUser($userId, 'user1', 25);

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($userId)
			->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedUser) use ($newPostCount) {
				$this->assertEquals($newPostCount, $updatedUser->getPostCount());
				return $updatedUser;
			});

		$response = $this->controller->update($userId, $newPostCount);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($userId, $data['id']);
	}

	public function testUpdateForumUserReturnsNotFoundWhenUserDoesNotExist(): void {
		$userId = 999;

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($userId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->update($userId, 50);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testDestroyForumUserSuccessfully(): void {
		$userId = 1;
		$user = $this->createForumUser($userId, 'user1', 10);

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($userId)
			->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('delete')
			->with($user);

		$response = $this->controller->destroy($userId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['success' => true], $response->getData());
	}

	public function testDestroyForumUserReturnsNotFoundWhenUserDoesNotExist(): void {
		$userId = 999;

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($userId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->destroy($userId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	private function createForumUser(int $id, string $userId, int $postCount): ForumUser {
		$forumUser = new ForumUser();
		$forumUser->setId($id);
		$forumUser->setUserId($userId);
		$forumUser->setPostCount($postCount);
		$forumUser->setCreatedAt(time());
		$forumUser->setUpdatedAt(time());
		return $forumUser;
	}
}
