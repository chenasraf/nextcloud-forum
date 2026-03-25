<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\ModerationService;
use OCA\Forum\Service\StatsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ModerationServiceTest extends TestCase {
	private ModerationService $service;

	/** @var ThreadMapper&MockObject */
	private ThreadMapper $threadMapper;
	/** @var PostMapper&MockObject */
	private PostMapper $postMapper;
	/** @var StatsService&MockObject */
	private StatsService $statsService;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->statsService = $this->createMock(StatsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ModerationService(
			$this->threadMapper,
			$this->postMapper,
			$this->statsService,
			$this->logger,
		);
	}

	public function testRestoreThreadSuccess(): void {
		$thread = new Thread();
		$thread->setId(1);
		$thread->setCategoryId(5);
		$thread->setAuthorId('alice');
		$thread->setDeletedAt(1000);

		$this->threadMapper->expects($this->once())
			->method('findIncludingDeleted')
			->with(1)
			->willReturn($thread);

		$this->threadMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($t) {
				$this->assertNull($t->getDeletedAt());
				$this->assertNotNull($t->getUpdatedAt());
				return $t;
			});

		$this->statsService->expects($this->once())->method('rebuildCategoryStats')->with(5);
		$this->statsService->expects($this->once())->method('rebuildThreadStats')->with(1);
		$this->statsService->expects($this->once())->method('rebuildUserStats')->with('alice');

		$result = $this->service->restoreThread(1);
		$this->assertNull($result->getDeletedAt());
	}

	public function testRestoreThreadAlreadyActiveThrows(): void {
		$thread = new Thread();
		$thread->setId(1);
		$thread->setDeletedAt(null);

		$this->threadMapper->method('findIncludingDeleted')->willReturn($thread);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Thread is not deleted');
		$this->service->restoreThread(1);
	}

	public function testRestoreThreadNotFoundThrows(): void {
		$this->threadMapper->method('findIncludingDeleted')
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException(''));

		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		$this->service->restoreThread(999);
	}

	public function testRestorePostSuccess(): void {
		$post = new Post();
		$post->setId(10);
		$post->setThreadId(1);
		$post->setAuthorId('bob');
		$post->setIsFirstPost(false);
		$post->setDeletedAt(2000);

		$thread = new Thread();
		$thread->setId(1);
		$thread->setCategoryId(5);

		$this->postMapper->expects($this->once())
			->method('findIncludingDeleted')
			->with(10)
			->willReturn($post);

		$this->postMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($p) {
				$this->assertNull($p->getDeletedAt());
				return $p;
			});

		$this->threadMapper->expects($this->once())
			->method('findIncludingDeleted')
			->with(1)
			->willReturn($thread);

		$this->statsService->expects($this->once())->method('rebuildThreadStats')->with(1);
		$this->statsService->expects($this->once())->method('rebuildCategoryStats')->with(5);
		$this->statsService->expects($this->once())->method('rebuildUserStats')->with('bob');

		$result = $this->service->restorePost(10);
		$this->assertNull($result->getDeletedAt());
	}

	public function testRestorePostAlreadyActiveThrows(): void {
		$post = new Post();
		$post->setId(10);
		$post->setDeletedAt(null);

		$this->postMapper->method('findIncludingDeleted')->willReturn($post);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Post is not deleted');
		$this->service->restorePost(10);
	}

	public function testRestorePostFirstPostThrows(): void {
		$post = new Post();
		$post->setId(10);
		$post->setIsFirstPost(true);
		$post->setDeletedAt(2000);

		$this->postMapper->method('findIncludingDeleted')->willReturn($post);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('First posts must be restored via thread restore');
		$this->service->restorePost(10);
	}
}
