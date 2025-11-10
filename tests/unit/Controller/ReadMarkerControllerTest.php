<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ReadMarkerController;
use OCA\Forum\Db\ReadMarker;
use OCA\Forum\Db\ReadMarkerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReadMarkerControllerTest extends TestCase {
	private ReadMarkerController $controller;
	private ReadMarkerMapper $readMarkerMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->readMarkerMapper = $this->createMock(ReadMarkerMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ReadMarkerController(
			Application::APP_ID,
			$this->request,
			$this->readMarkerMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testIndexReturnsReadMarkersForCurrentUser(): void {
		$userId = 'user1';
		$marker1 = $this->createReadMarker(1, $userId, 1, 10);
		$marker2 = $this->createReadMarker(2, $userId, 2, 20);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->readMarkerMapper->expects($this->once())
			->method('findByUserId')
			->with($userId)
			->willReturn([$marker1, $marker2]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testIndexReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testShowReturnsReadMarkerForThread(): void {
		$userId = 'user1';
		$threadId = 1;
		$marker = $this->createReadMarker(1, $userId, $threadId, 10);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->readMarkerMapper->expects($this->once())
			->method('findByUserAndThread')
			->with($userId, $threadId)
			->willReturn($marker);

		$response = $this->controller->show($threadId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['threadId']);
		$this->assertEquals(10, $data['lastReadPostId']);
	}

	public function testShowReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->show($threadId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testShowReturnsNotFoundWhenMarkerDoesNotExist(): void {
		$userId = 'user1';
		$threadId = 999;

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->readMarkerMapper->expects($this->once())
			->method('findByUserAndThread')
			->with($userId, $threadId)
			->willThrowException(new DoesNotExistException('Read marker not found'));

		$response = $this->controller->show($threadId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Read marker not found'], $response->getData());
	}

	public function testCreateInsertsNewMarkerWhenNotExists(): void {
		$userId = 'user1';
		$threadId = 1;
		$lastReadPostId = 10;

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$createdMarker = $this->createReadMarker(1, $userId, $threadId, $lastReadPostId);

		// Mock createOrUpdate method
		$this->readMarkerMapper->expects($this->once())
			->method('createOrUpdate')
			->with($userId, $threadId, $lastReadPostId)
			->willReturn($createdMarker);

		$response = $this->controller->create($threadId, $lastReadPostId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['threadId']);
		$this->assertEquals($lastReadPostId, $data['lastReadPostId']);
	}

	public function testCreateUpdatesExistingMarker(): void {
		$userId = 'user1';
		$threadId = 1;
		$oldPostId = 5;
		$newPostId = 15;

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$existingMarker = $this->createReadMarker(1, $userId, $threadId, $oldPostId);

		// Update the marker with new post ID
		$existingMarker->setLastReadPostId($newPostId);

		// Mock createOrUpdate method
		$this->readMarkerMapper->expects($this->once())
			->method('createOrUpdate')
			->with($userId, $threadId, $newPostId)
			->willReturn($existingMarker);

		$response = $this->controller->create($threadId, $newPostId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($threadId, $data['threadId']);
	}

	public function testCreateReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$threadId = 1;
		$lastReadPostId = 10;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create($threadId, $lastReadPostId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testDestroyDeletesMarkerSuccessfully(): void {
		$markerId = 1;
		$marker = $this->createReadMarker($markerId, 'user1', 1, 10);

		$this->readMarkerMapper->expects($this->once())
			->method('find')
			->with($markerId)
			->willReturn($marker);

		$this->readMarkerMapper->expects($this->once())
			->method('delete')
			->with($marker);

		$response = $this->controller->destroy($markerId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['success' => true], $response->getData());
	}

	public function testDestroyReturnsNotFoundWhenMarkerDoesNotExist(): void {
		$markerId = 999;

		$this->readMarkerMapper->expects($this->once())
			->method('find')
			->with($markerId)
			->willThrowException(new DoesNotExistException('Read marker not found'));

		$response = $this->controller->destroy($markerId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Read marker not found'], $response->getData());
	}

	private function createReadMarker(int $id, string $userId, int $threadId, int $lastReadPostId): ReadMarker {
		$marker = new ReadMarker();
		$marker->setId($id);
		$marker->setUserId($userId);
		$marker->setThreadId($threadId);
		$marker->setLastReadPostId($lastReadPostId);
		$marker->setReadAt(time());
		return $marker;
	}
}
