<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\PostHistoryController;
use OCA\Forum\Service\PostHistoryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostHistoryControllerTest extends TestCase {
	private PostHistoryController $controller;
	/** @var PostHistoryService&MockObject */
	private PostHistoryService $postHistoryService;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->postHistoryService = $this->createMock(PostHistoryService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new PostHistoryController(
			Application::APP_ID,
			$this->request,
			$this->postHistoryService,
			$this->logger
		);
	}

	public function testGetHistoryReturnsHistorySuccessfully(): void {
		$postId = 1;

		$historyData = [
			'current' => [
				'id' => 1,
				'content' => 'Current content',
				'authorId' => 'user1',
				'editedAt' => 3000000,
				'createdAt' => 1000000,
				'author' => ['userId' => 'user1', 'displayName' => 'User 1'],
			],
			'history' => [
				[
					'id' => 1,
					'postId' => 1,
					'content' => 'Second version',
					'editedBy' => 'user1',
					'editedAt' => 2000000,
					'editor' => ['userId' => 'user1', 'displayName' => 'User 1'],
				],
				[
					'id' => 2,
					'postId' => 1,
					'content' => 'Original content',
					'editedBy' => 'user1',
					'editedAt' => 1000000,
					'editor' => ['userId' => 'user1', 'displayName' => 'User 1'],
				],
			],
		];

		$this->postHistoryService->expects($this->once())
			->method('getPostHistory')
			->with($postId)
			->willReturn($historyData);

		$response = $this->controller->getHistory($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('current', $data);
		$this->assertArrayHasKey('history', $data);
		$this->assertEquals('Current content', $data['current']['content']);
		$this->assertCount(2, $data['history']);
	}

	public function testGetHistoryReturnsNotFoundWhenPostDoesNotExist(): void {
		$postId = 999;

		$this->postHistoryService->expects($this->once())
			->method('getPostHistory')
			->with($postId)
			->willThrowException(new DoesNotExistException('Post not found'));

		$response = $this->controller->getHistory($postId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Post not found'], $response->getData());
	}

	public function testGetHistoryReturnsEmptyHistoryForNeverEditedPost(): void {
		$postId = 1;

		$historyData = [
			'current' => [
				'id' => 1,
				'content' => 'Original content',
				'authorId' => 'user1',
				'editedAt' => null,
				'createdAt' => 1000000,
				'isEdited' => false,
				'author' => ['userId' => 'user1', 'displayName' => 'User 1'],
			],
			'history' => [],
		];

		$this->postHistoryService->expects($this->once())
			->method('getPostHistory')
			->with($postId)
			->willReturn($historyData);

		$response = $this->controller->getHistory($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(0, $data['history']);
	}

	public function testGetHistoryHandlesException(): void {
		$postId = 1;

		$this->postHistoryService->expects($this->once())
			->method('getPostHistory')
			->with($postId)
			->willThrowException(new \Exception('Database error'));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Error fetching post history'));

		$response = $this->controller->getHistory($postId);

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertEquals(['error' => 'Failed to fetch post history'], $response->getData());
	}

	public function testGetHistoryShowsModeratorEdits(): void {
		$postId = 1;

		$historyData = [
			'current' => [
				'id' => 1,
				'content' => 'Content edited by moderator',
				'authorId' => 'user1',
				'editedAt' => 2000000,
				'createdAt' => 1000000,
				'author' => ['userId' => 'user1', 'displayName' => 'User 1'],
			],
			'history' => [
				[
					'id' => 1,
					'postId' => 1,
					'content' => 'Original user content',
					'editedBy' => 'moderator1', // Different from author
					'editedAt' => 1000000,
					'editor' => ['userId' => 'moderator1', 'displayName' => 'Moderator 1'],
				],
			],
		];

		$this->postHistoryService->expects($this->once())
			->method('getPostHistory')
			->with($postId)
			->willReturn($historyData);

		$response = $this->controller->getHistory($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();

		// Verify the editor info shows the moderator
		$this->assertEquals('moderator1', $data['history'][0]['editedBy']);
		$this->assertEquals('Moderator 1', $data['history'][0]['editor']['displayName']);

		// Verify the post author is still the original user
		$this->assertEquals('user1', $data['current']['authorId']);
	}
}
