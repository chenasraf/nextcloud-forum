<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ForumUserController;
use OCA\Forum\Db\ForumUser;
use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Service\UserService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ForumUserControllerTest extends TestCase {
	private ForumUserController $controller;
	/** @var ForumUserMapper&MockObject */
	private ForumUserMapper $forumUserMapper;
	/** @var UserService&MockObject */
	private UserService $userService;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->forumUserMapper = $this->createMock(ForumUserMapper::class);
		$this->userService = $this->createMock(UserService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ForumUserController(
			Application::APP_ID,
			$this->request,
			$this->forumUserMapper,
			$this->userService,
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
		$nextcloudUserId = 'user1';
		$user = $this->createForumUser(1, $nextcloudUserId, 10);

		$this->forumUserMapper->expects($this->once())
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

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->show($nextcloudUserId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testShowWithMeReturnsCurrentUserSuccessfully(): void {
		$nextcloudUserId = 'current-user';
		$forumUser = $this->createForumUser(1, $nextcloudUserId, 15);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($nextcloudUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willReturn($forumUser);

		$response = $this->controller->show('me');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
	}

	public function testShowWithMeReturnsNotFoundWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->show('me');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testShowWithMeReturnsNotFoundWhenForumUserDoesNotExist(): void {
		$nextcloudUserId = 'user-without-forum-profile';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($nextcloudUserId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->forumUserMapper->expects($this->once())
			->method('find')
			->with($nextcloudUserId)
			->willThrowException(new DoesNotExistException('Forum user not found'));

		$response = $this->controller->show('me');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Forum user not found'], $response->getData());
	}

	public function testCreateForumUserSuccessfully(): void {
		$nextcloudUserId = 'new-user';
		$createdUser = $this->createForumUser(1, $nextcloudUserId, 0);

		$this->forumUserMapper->expects($this->once())
			->method('createOrUpdate')
			->with($nextcloudUserId)
			->willReturn($createdUser);

		$response = $this->controller->create($nextcloudUserId);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($nextcloudUserId, $data['userId']);
		$this->assertEquals(0, $data['postCount']);
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
