<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ReactionController;
use OCA\Forum\Db\Reaction;
use OCA\Forum\Db\ReactionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReactionControllerTest extends TestCase {
	private ReactionController $controller;
	/** @var ReactionMapper&MockObject */
	private ReactionMapper $reactionMapper;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->reactionMapper = $this->createMock(ReactionMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ReactionController(
			Application::APP_ID,
			$this->request,
			$this->reactionMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testByPostReturnsReactionsSuccessfully(): void {
		$postId = 1;
		$reaction1 = $this->createMockReaction(1, $postId, 'user1', '👍');
		$reaction2 = $this->createMockReaction(2, $postId, 'user2', '❤️');

		$this->reactionMapper->expects($this->once())
			->method('findByPostId')
			->with($postId)
			->willReturn([$reaction1, $reaction2]);

		$response = $this->controller->byPost($postId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testByPostsReturnsReactionsForMultiplePosts(): void {
		$postIds = [1, 2];
		$reaction1 = $this->createMockReaction(1, 1, 'user1', '👍');
		$reaction2 = $this->createMockReaction(2, 2, 'user2', '❤️');

		$this->reactionMapper->expects($this->once())
			->method('findByPostIds')
			->with($postIds)
			->willReturn([$reaction1, $reaction2]);

		$response = $this->controller->byPosts($postIds);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testShowReturnsReactionSuccessfully(): void {
		$reactionId = 1;
		$reaction = $this->createMockReaction($reactionId, 1, 'user1', '👍');

		$this->reactionMapper->expects($this->once())
			->method('find')
			->with($reactionId)
			->willReturn($reaction);

		$response = $this->controller->show($reactionId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($reactionId, $data['id']);
	}

	public function testShowReturnsNotFoundWhenReactionDoesNotExist(): void {
		$reactionId = 999;

		$this->reactionMapper->expects($this->once())
			->method('find')
			->with($reactionId)
			->willThrowException(new DoesNotExistException('Reaction not found'));

		$response = $this->controller->show($reactionId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Reaction not found'], $response->getData());
	}

	public function testCreateReactionSuccessfully(): void {
		$postId = 1;
		$reactionType = '👍';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$createdReaction = $this->createMockReaction(1, $postId, $userId, $reactionType);

		$this->reactionMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($reaction) use ($createdReaction) {
				return $createdReaction;
			});

		$response = $this->controller->create($postId, $reactionType);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['id']);
		$this->assertEquals($postId, $data['postId']);
		$this->assertEquals($userId, $data['userId']);
		$this->assertEquals($reactionType, $data['reactionType']);
	}

	public function testCreateReactionReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$postId = 1;
		$reactionType = '👍';

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create($postId, $reactionType);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testDestroyReactionSuccessfully(): void {
		$reactionId = 1;
		$reaction = $this->createMockReaction($reactionId, 1, 'user1', '👍');

		$this->reactionMapper->expects($this->once())
			->method('find')
			->with($reactionId)
			->willReturn($reaction);

		$this->reactionMapper->expects($this->once())
			->method('delete')
			->with($reaction);

		$response = $this->controller->destroy($reactionId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['success' => true], $response->getData());
	}

	public function testDestroyReactionReturnsNotFoundWhenReactionDoesNotExist(): void {
		$reactionId = 999;

		$this->reactionMapper->expects($this->once())
			->method('find')
			->with($reactionId)
			->willThrowException(new DoesNotExistException('Reaction not found'));

		$response = $this->controller->destroy($reactionId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'Reaction not found'], $response->getData());
	}

	public function testToggleAddsReactionWhenNotExists(): void {
		$postId = 1;
		$reactionType = '👍';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		// First call to find existing reaction - should throw exception
		$this->reactionMapper->expects($this->once())
			->method('findByPostUserAndType')
			->with($postId, $userId, $reactionType)
			->willThrowException(new DoesNotExistException('Reaction not found'));

		// Second call to insert new reaction
		$createdReaction = $this->createMockReaction(1, $postId, $userId, $reactionType);
		$this->reactionMapper->expects($this->once())
			->method('insert')
			->willReturn($createdReaction);

		$response = $this->controller->toggle($postId, $reactionType);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals('added', $data['action']);
		$this->assertArrayHasKey('reaction', $data);
		$this->assertEquals($reactionType, $data['reaction']['reactionType']);
	}

	public function testToggleRemovesReactionWhenExists(): void {
		$postId = 1;
		$reactionType = '👍';
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$existingReaction = $this->createMockReaction(1, $postId, $userId, $reactionType);

		$this->reactionMapper->expects($this->once())
			->method('findByPostUserAndType')
			->with($postId, $userId, $reactionType)
			->willReturn($existingReaction);

		$this->reactionMapper->expects($this->once())
			->method('delete')
			->with($existingReaction);

		$response = $this->controller->toggle($postId, $reactionType);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals('removed', $data['action']);
	}

	public function testToggleReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$postId = 1;
		$reactionType = '👍';

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->toggle($postId, $reactionType);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
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
}
