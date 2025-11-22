<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ThreadSubscriptionController;
use OCA\Forum\Db\ThreadSubscription;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ThreadSubscriptionControllerTest extends TestCase {
	private ThreadSubscriptionController $controller;
	private ThreadSubscriptionMapper $subscriptionMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->subscriptionMapper = $this->createMock(ThreadSubscriptionMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ThreadSubscriptionController(
			Application::APP_ID,
			$this->request,
			$this->subscriptionMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testSubscribeSuccessfully(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$subscription = $this->createMockSubscription(1, $userId, $threadId);

		$this->subscriptionMapper->expects($this->once())
			->method('subscribe')
			->with($userId, $threadId)
			->willReturn($subscription);

		$response = $this->controller->subscribe($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertArrayHasKey('subscription', $data);
		$this->assertEquals($userId, $data['subscription']['userId']);
		$this->assertEquals($threadId, $data['subscription']['threadId']);
	}

	public function testSubscribeReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->subscribe($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testUnsubscribeSuccessfully(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->subscriptionMapper->expects($this->once())
			->method('unsubscribe')
			->with($userId, $threadId);

		$response = $this->controller->unsubscribe($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testUnsubscribeReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->unsubscribe($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testIsSubscribedReturnsTrue(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->subscriptionMapper->expects($this->once())
			->method('isUserSubscribed')
			->with($userId, $threadId)
			->willReturn(true);

		$response = $this->controller->isSubscribed($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['isSubscribed']);
	}

	public function testIsSubscribedReturnsFalse(): void {
		$threadId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->subscriptionMapper->expects($this->once())
			->method('isUserSubscribed')
			->with($userId, $threadId)
			->willReturn(false);

		$response = $this->controller->isSubscribed($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['isSubscribed']);
	}

	public function testIsSubscribedReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->isSubscribed($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testGetUserSubscriptionsReturnsSubscriptionsSuccessfully(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$subscription1 = $this->createMockSubscription(1, $userId, 1);
		$subscription2 = $this->createMockSubscription(2, $userId, 2);
		$subscriptions = [$subscription1, $subscription2];

		$this->subscriptionMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn($subscriptions);

		$response = $this->controller->getUserSubscriptions();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals(1, $data[0]['id']);
		$this->assertEquals(2, $data[1]['id']);
		$this->assertEquals($userId, $data[0]['userId']);
		$this->assertEquals(1, $data[0]['threadId']);
	}

	public function testGetUserSubscriptionsHandlesEmptySubscriptions(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->subscriptionMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([]);

		$response = $this->controller->getUserSubscriptions();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(0, $data);
	}

	public function testGetUserSubscriptionsReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->getUserSubscriptions();

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	private function createMockSubscription(int $id, string $userId, int $threadId): ThreadSubscription {
		$subscription = new ThreadSubscription();
		$subscription->setId($id);
		$subscription->setUserId($userId);
		$subscription->setThreadId($threadId);
		$subscription->setCreatedAt(time());
		return $subscription;
	}
}
