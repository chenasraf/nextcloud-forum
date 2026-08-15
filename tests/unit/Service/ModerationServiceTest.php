<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\Bookmark;
use OCA\Forum\Db\BookmarkMapper;
use OCA\Forum\Db\Draft;
use OCA\Forum\Db\DraftMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostHistoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReactionMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCA\Forum\Service\ModerationService;
use OCA\Forum\Service\StatsService;
use OCP\IDBConnection;
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
	/** @var ReactionMapper&MockObject */
	private ReactionMapper $reactionMapper;
	/** @var PostHistoryMapper&MockObject */
	private PostHistoryMapper $postHistoryMapper;
	/** @var BookmarkMapper&MockObject */
	private BookmarkMapper $bookmarkMapper;
	/** @var ReadMarkerMapper&MockObject */
	private ReadMarkerMapper $readMarkerMapper;
	/** @var ThreadSubscriptionMapper&MockObject */
	private ThreadSubscriptionMapper $threadSubscriptionMapper;
	/** @var DraftMapper&MockObject */
	private DraftMapper $draftMapper;
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->statsService = $this->createMock(StatsService::class);
		$this->reactionMapper = $this->createMock(ReactionMapper::class);
		$this->postHistoryMapper = $this->createMock(PostHistoryMapper::class);
		$this->bookmarkMapper = $this->createMock(BookmarkMapper::class);
		$this->readMarkerMapper = $this->createMock(ReadMarkerMapper::class);
		$this->threadSubscriptionMapper = $this->createMock(ThreadSubscriptionMapper::class);
		$this->draftMapper = $this->createMock(DraftMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ModerationService(
			$this->threadMapper,
			$this->postMapper,
			$this->statsService,
			$this->reactionMapper,
			$this->postHistoryMapper,
			$this->bookmarkMapper,
			$this->readMarkerMapper,
			$this->threadSubscriptionMapper,
			$this->draftMapper,
			$this->db,
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

	public function testPermanentlyDeleteThreadSuccess(): void {
		$thread = new Thread();
		$thread->setId(1);
		$thread->setCategoryId(5);
		$thread->setAuthorId('alice');
		$thread->setDeletedAt(1000);

		$this->threadMapper->expects($this->once())
			->method('findIncludingDeleted')
			->with(1)
			->willReturn($thread);

		$this->postMapper->expects($this->once())
			->method('findIdsByThreadId')
			->with(1)
			->willReturn([10, 11]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->reactionMapper->expects($this->once())->method('deleteByPostIds')->with([10, 11]);
		$this->postHistoryMapper->expects($this->once())->method('deleteByPostIds')->with([10, 11]);
		$this->bookmarkMapper->expects($this->once())
			->method('deleteByEntityTypeAndIds')
			->with(Bookmark::ENTITY_TYPE_POST, [10, 11]);
		$this->postMapper->expects($this->once())->method('deleteByThreadId')->with(1);
		$this->bookmarkMapper->expects($this->once())
			->method('deleteByEntity')
			->with(Bookmark::ENTITY_TYPE_THREAD, 1);
		$this->readMarkerMapper->expects($this->once())->method('deleteByThreadId')->with(1);
		$this->threadSubscriptionMapper->expects($this->once())->method('deleteByThreadId')->with(1);
		$this->draftMapper->expects($this->once())
			->method('deleteByEntityTypeAndParentId')
			->with(Draft::ENTITY_TYPE_POST, 1);
		$this->threadMapper->expects($this->once())->method('delete')->with($thread);

		$this->service->permanentlyDeleteThread(1);
	}

	public function testPermanentlyDeleteThreadNotDeletedThrows(): void {
		$thread = new Thread();
		$thread->setId(1);
		$thread->setDeletedAt(null);

		$this->threadMapper->method('findIncludingDeleted')->willReturn($thread);
		$this->db->expects($this->never())->method('beginTransaction');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Thread is not deleted');
		$this->service->permanentlyDeleteThread(1);
	}

	public function testPermanentlyDeleteThreadNotFoundThrows(): void {
		$this->threadMapper->method('findIncludingDeleted')
			->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException(''));

		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		$this->service->permanentlyDeleteThread(999);
	}

	public function testPermanentlyDeleteThreadRollsBackOnError(): void {
		$thread = new Thread();
		$thread->setId(1);
		$thread->setDeletedAt(1000);

		$this->threadMapper->method('findIncludingDeleted')->willReturn($thread);
		$this->postMapper->method('findIdsByThreadId')->willReturn([]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$this->postMapper->method('deleteByThreadId')
			->willThrowException(new \RuntimeException('db error'));

		$this->expectException(\RuntimeException::class);
		$this->service->permanentlyDeleteThread(1);
	}

	public function testPermanentlyDeleteReplySuccess(): void {
		$post = new Post();
		$post->setId(10);
		$post->setThreadId(1);
		$post->setIsFirstPost(false);
		$post->setDeletedAt(2000);

		$this->postMapper->expects($this->once())
			->method('findIncludingDeleted')
			->with(10)
			->willReturn($post);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$this->reactionMapper->expects($this->once())->method('deleteByPostIds')->with([10]);
		$this->postHistoryMapper->expects($this->once())->method('deleteByPostId')->with(10);
		$this->bookmarkMapper->expects($this->once())
			->method('deleteByEntity')
			->with(Bookmark::ENTITY_TYPE_POST, 10);
		$this->postMapper->expects($this->once())->method('deleteById')->with(10);

		$this->service->permanentlyDeleteReply(10);
	}

	public function testPermanentlyDeleteReplyNotDeletedThrows(): void {
		$post = new Post();
		$post->setId(10);
		$post->setDeletedAt(null);

		$this->postMapper->method('findIncludingDeleted')->willReturn($post);
		$this->db->expects($this->never())->method('beginTransaction');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Post is not deleted');
		$this->service->permanentlyDeleteReply(10);
	}

	public function testPermanentlyDeleteReplyFirstPostThrows(): void {
		$post = new Post();
		$post->setId(10);
		$post->setIsFirstPost(true);
		$post->setDeletedAt(2000);

		$this->postMapper->method('findIncludingDeleted')->willReturn($post);
		$this->db->expects($this->never())->method('beginTransaction');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('First posts must be deleted via thread deletion');
		$this->service->permanentlyDeleteReply(10);
	}
}
