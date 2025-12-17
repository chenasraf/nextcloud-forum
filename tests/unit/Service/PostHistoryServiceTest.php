<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostHistory;
use OCA\Forum\Db\PostHistoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\PostHistoryService;
use OCA\Forum\Service\UserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PostHistoryServiceTest extends TestCase {
	private PostHistoryService $service;
	/** @var PostHistoryMapper&MockObject */
	private PostHistoryMapper $postHistoryMapper;
	/** @var PostMapper&MockObject */
	private PostMapper $postMapper;
	/** @var BBCodeService&MockObject */
	private BBCodeService $bbCodeService;
	/** @var BBCodeMapper&MockObject */
	private BBCodeMapper $bbCodeMapper;
	/** @var UserService&MockObject */
	private UserService $userService;

	protected function setUp(): void {
		$this->postHistoryMapper = $this->createMock(PostHistoryMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->bbCodeService = $this->createMock(BBCodeService::class);
		$this->bbCodeMapper = $this->createMock(BBCodeMapper::class);
		$this->userService = $this->createMock(UserService::class);

		$this->service = new PostHistoryService(
			$this->postHistoryMapper,
			$this->postMapper,
			$this->bbCodeService,
			$this->bbCodeMapper,
			$this->userService
		);
	}

	public function testSaveHistoryCreatesHistoryEntry(): void {
		$post = new Post();
		$post->setId(1);
		$post->setContent('Original content');
		$post->setCreatedAt(1000000);
		$post->setEditedAt(null);

		$editedBy = 'user1';

		$this->postHistoryMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (PostHistory $history) use ($post, $editedBy) {
				$this->assertEquals($post->getId(), $history->getPostId());
				$this->assertEquals('Original content', $history->getContent());
				$this->assertEquals($editedBy, $history->getEditedBy());
				// Should use createdAt since editedAt is null
				$this->assertEquals(1000000, $history->getEditedAt());
				return $history;
			});

		$result = $this->service->saveHistory($post, $editedBy);

		$this->assertInstanceOf(PostHistory::class, $result);
	}

	public function testSaveHistoryUsesEditedAtWhenAvailable(): void {
		$post = new Post();
		$post->setId(1);
		$post->setContent('Edited content');
		$post->setCreatedAt(1000000);
		$post->setEditedAt(2000000); // Post was previously edited

		$editedBy = 'moderator1';

		$this->postHistoryMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (PostHistory $history) {
				// Should use editedAt instead of createdAt
				$this->assertEquals(2000000, $history->getEditedAt());
				return $history;
			});

		$this->service->saveHistory($post, $editedBy);
	}

	public function testGetPostHistoryReturnsCurrentAndHistoricalVersions(): void {
		$postId = 1;

		// Create mock current post
		$post = new Post();
		$post->setId($postId);
		$post->setThreadId(1);
		$post->setAuthorId('user1');
		$post->setContent('Current content');
		$post->setIsEdited(true);
		$post->setEditedAt(3000000);
		$post->setCreatedAt(1000000);
		$post->setUpdatedAt(3000000);

		// Create mock history entries
		$history1 = new PostHistory();
		$history1->setId(1);
		$history1->setPostId($postId);
		$history1->setContent('Second version');
		$history1->setEditedBy('user1');
		$history1->setEditedAt(2000000);

		$history2 = new PostHistory();
		$history2->setId(2);
		$history2->setPostId($postId);
		$history2->setContent('Original content');
		$history2->setEditedBy('user1');
		$history2->setEditedAt(1000000);

		$this->postMapper->expects($this->once())
			->method('find')
			->with($postId)
			->willReturn($post);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllEnabled')
			->willReturn([]);

		$this->bbCodeService->method('parse')
			->willReturnCallback(function ($content) {
				return $content; // Return content unchanged for test
			});

		$this->userService->expects($this->once())
			->method('enrichUserData')
			->with('user1')
			->willReturn(['userId' => 'user1', 'displayName' => 'User 1']);

		$this->postHistoryMapper->expects($this->once())
			->method('findByPostId')
			->with($postId)
			->willReturn([$history1, $history2]); // Ordered by editedAt DESC

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with(['user1'])
			->willReturn([
				'user1' => ['userId' => 'user1', 'displayName' => 'User 1'],
			]);

		$result = $this->service->getPostHistory($postId);

		$this->assertArrayHasKey('current', $result);
		$this->assertArrayHasKey('history', $result);
		$this->assertEquals('Current content', $result['current']['content']);
		$this->assertCount(2, $result['history']);
		$this->assertEquals('Second version', $result['history'][0]['content']);
		$this->assertEquals('Original content', $result['history'][1]['content']);
	}

	public function testGetPostHistoryIncludesEditorInfo(): void {
		$postId = 1;

		$post = new Post();
		$post->setId($postId);
		$post->setThreadId(1);
		$post->setAuthorId('user1');
		$post->setContent('Current content');
		$post->setIsEdited(true);
		$post->setEditedAt(2000000);
		$post->setCreatedAt(1000000);
		$post->setUpdatedAt(2000000);

		// History entry edited by a moderator
		$history = new PostHistory();
		$history->setId(1);
		$history->setPostId($postId);
		$history->setContent('Original content');
		$history->setEditedBy('moderator1');
		$history->setEditedAt(1000000);

		$this->postMapper->method('find')->willReturn($post);
		$this->bbCodeMapper->method('findAllEnabled')->willReturn([]);
		$this->bbCodeService->method('parse')->willReturnArgument(0);
		$this->userService->method('enrichUserData')->willReturn(['userId' => 'user1', 'displayName' => 'User 1']);
		$this->postHistoryMapper->method('findByPostId')->willReturn([$history]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with(['moderator1'])
			->willReturn([
				'moderator1' => ['userId' => 'moderator1', 'displayName' => 'Moderator 1'],
			]);

		$result = $this->service->getPostHistory($postId);

		$this->assertEquals('moderator1', $result['history'][0]['editedBy']);
		$this->assertEquals('Moderator 1', $result['history'][0]['editor']['displayName']);
	}

	public function testHasHistoryReturnsTrueWhenHistoryExists(): void {
		$postId = 1;

		$this->postHistoryMapper->expects($this->once())
			->method('countByPostId')
			->with($postId)
			->willReturn(2);

		$result = $this->service->hasHistory($postId);

		$this->assertTrue($result);
	}

	public function testHasHistoryReturnsFalseWhenNoHistory(): void {
		$postId = 1;

		$this->postHistoryMapper->expects($this->once())
			->method('countByPostId')
			->with($postId)
			->willReturn(0);

		$result = $this->service->hasHistory($postId);

		$this->assertFalse($result);
	}

	public function testGetHistoryCountReturnsCorrectCount(): void {
		$postId = 1;

		$this->postHistoryMapper->expects($this->once())
			->method('countByPostId')
			->with($postId)
			->willReturn(5);

		$result = $this->service->getHistoryCount($postId);

		$this->assertEquals(5, $result);
	}
}
