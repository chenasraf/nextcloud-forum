<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ForumUserController;
use OCA\Forum\Db\UserStats;
use OCA\Forum\Db\UserStatsMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ForumUserControllerTest extends TestCase {
	private ForumUserController $controller;
	private UserStatsMapper $userStatsMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userStatsMapper = $this->createMock(UserStatsMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ForumUserController(
			Application::APP_ID,
			$this->request,
			$this->userStatsMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testIndexReturnsAllForumUsersSuccessfully(): void {
		$user1 = $this->createForumUser(1, 'user1', 10);
		$user2 = $this->createForumUser(2, 'user2', 25);

		$this->userStatsMapper->expects($this->once())
			->method('findAll')
			->willReturn([$user1, $user2]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testShowReturnsForumUserSuccessfully(): void {
		$nextcloudUserId = 'user1';
		$user = $this->createForumUser(1, $nextcloudUserId, 10);

		$this->userStatsMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willReturn($user);

		$response = $this->controller->show($nextcloudUserId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
		$this->assertEquals(10, $data['postCount']);
	}

	public function testShowReturnsNotFoundWhenUserDoesNotExist(): void {
		$nextcloudUserId = 'non-existent-user';

		$this->userStatsMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willThrowException(new DoesNotExistException('User stats not found'));

		$response = $this->controller->show($nextcloudUserId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'User stats not found'], $response->getData());
	}

	public function testShowWithMeReturnsCurrentUserSuccessfully(): void {
		$nextcloudUserId = 'current-user';
		$forumUser = $this->createForumUser(1, $nextcloudUserId, 15);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($nextcloudUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->userStatsMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willReturn($forumUser);

		$response = $this->controller->show('me');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
	}

	public function testShowWithMeReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->show('me');

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testShowWithMeReturnsNotFoundWhenForumUserDoesNotExist(): void {
		$nextcloudUserId = 'user-without-forum-profile';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($nextcloudUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->userStatsMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willThrowException(new DoesNotExistException('User stats not found'));

		$response = $this->controller->show('me');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'User stats not found'], $response->getData());
	}

	public function testCreateForumUserSuccessfully(): void {
		$nextcloudUserId = 'new-user';
		$createdUser = $this->createForumUser(1, $nextcloudUserId, 0);

		$this->userStatsMapper->expects($this->once())
			->method('createOrUpdate')
			->with($nextcloudUserId)
			->willReturn($createdUser);

		$response = $this->controller->create($nextcloudUserId);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
		$this->assertEquals(0, $data['postCount']);
	}

	private function createForumUser(int $id, string $userId, int $postCount): UserStats {
		$userStats = new UserStats();
		$userStats->setId($id);
		$userStats->setUserId($userId);
		$userStats->setPostCount($postCount);
		$userStats->setCreatedAt(time());
		$userStats->setUpdatedAt(time());
		return $userStats;
	}
}
