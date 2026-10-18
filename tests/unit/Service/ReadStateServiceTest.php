<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReadMarker;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Service\PermissionService;
use OCA\Forum\Service\ReadStateService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReadStateServiceTest extends TestCase {
	private ReadStateService $service;
	/** @var ReadMarkerMapper&MockObject */
	private ReadMarkerMapper $readMarkerMapper;
	/** @var PostMapper&MockObject */
	private PostMapper $postMapper;
	/** @var CategoryMapper&MockObject */
	private CategoryMapper $categoryMapper;
	/** @var PermissionService&MockObject */
	private PermissionService $permissionService;

	protected function setUp(): void {
		$this->readMarkerMapper = $this->createMock(ReadMarkerMapper::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$this->service = new ReadStateService(
			$this->readMarkerMapper,
			$this->postMapper,
			$this->categoryMapper,
			$this->permissionService,
		);
	}

	public function testGetThreadStatesReturnsEmptyForNoThreads(): void {
		$this->postMapper->expects($this->never())->method('countUnreadByThreads');

		$this->assertSame([], $this->service->getThreadStates('user1', []));
	}

	public function testGetThreadStatesCountsUnreadRepliesOfSeenThreads(): void {
		$thread = $this->createThread(1, 10, 1000);
		$this->postMapper->method('countUnreadByThreads')->with('user1', [1])->willReturn([1 => 4]);
		$this->readMarkerMapper->method('findByUserAndThreads')->willReturn([$this->createThreadMarker(1, 50)]);

		$states = $this->service->getThreadStates('user1', [$thread]);

		$this->assertSame(['isUnread' => true, 'unreadReplyCount' => 4], $states[1]);
	}

	public function testGetThreadStatesMarksFullyReadThreadsAsRead(): void {
		$thread = $this->createThread(1, 10, 1000);
		$this->postMapper->method('countUnreadByThreads')->willReturn([]);
		$this->readMarkerMapper->method('findByUserAndThreads')->willReturn([$this->createThreadMarker(1, 50)]);

		$states = $this->service->getThreadStates('user1', [$thread]);

		$this->assertSame(['isUnread' => false, 'unreadReplyCount' => 0], $states[1]);
	}

	public function testGetThreadStatesHasNoReplyCountForUnseenThreads(): void {
		$thread = $this->createThread(1, 10, 1000);
		$this->postMapper->method('countUnreadByThreads')->willReturn([1 => 6]);
		$this->readMarkerMapper->method('findByUserAndThreads')->willReturn([]);

		$states = $this->service->getThreadStates('user1', [$thread]);

		$this->assertSame(['isUnread' => true, 'unreadReplyCount' => null], $states[1]);
	}

	public function testGetThreadStatesTreatsThreadsCoveredByWatermarkAsSeen(): void {
		$olderThread = $this->createThread(1, 10, 1000);
		$newerThread = $this->createThread(2, 10, 3000);
		$this->postMapper->method('countUnreadByThreads')->willReturn([1 => 2, 2 => 1]);
		$this->readMarkerMapper->method('findByUserAndThreads')->willReturn([]);
		$this->readMarkerMapper->method('findCategoryMarkersByUserId')->willReturn([$this->createCategoryMarker(10, 2000)]);

		$states = $this->service->getThreadStates('user1', [$olderThread, $newerThread]);

		$this->assertSame(2, $states[1]['unreadReplyCount']);
		$this->assertNull($states[2]['unreadReplyCount']);
	}

	public function testGetLastReadPostIdUsesThreadMarkerWithoutWatermark(): void {
		$this->readMarkerMapper->method('findByUserAndThread')->willReturn($this->createThreadMarker(1, 50));
		$this->postMapper->expects($this->never())->method('findLastIdCreatedUpTo');

		$this->assertSame(50, $this->service->getLastReadPostId('user1', 1, 10));
	}

	public function testGetLastReadPostIdReturnsNullWhenNothingIsRead(): void {
		$this->readMarkerMapper->method('findByUserAndThread')->willThrowException(new DoesNotExistException(''));

		$this->assertNull($this->service->getLastReadPostId('user1', 1, 10));
	}

	public function testGetLastReadPostIdUsesWatermarkWhenItCoversMorePosts(): void {
		$this->readMarkerMapper->method('findByUserAndThread')->willReturn($this->createThreadMarker(1, 50));
		$this->readMarkerMapper->method('findCategoryMarkersByUserId')->willReturn([$this->createCategoryMarker(10, 2000)]);
		$this->postMapper->method('findLastIdCreatedUpTo')->with(1, 2000)->willReturn(80);

		$this->assertSame(80, $this->service->getLastReadPostId('user1', 1, 10));
	}

	public function testGetLastReadPostIdKeepsThreadMarkerWhenItIsAheadOfWatermark(): void {
		$this->readMarkerMapper->method('findByUserAndThread')->willReturn($this->createThreadMarker(1, 90));
		$this->readMarkerMapper->method('findCategoryMarkersByUserId')->willReturn([$this->createCategoryMarker(10, 2000)]);
		$this->postMapper->method('findLastIdCreatedUpTo')->willReturn(80);

		$this->assertSame(90, $this->service->getLastReadPostId('user1', 1, 10));
	}

	public function testGetLastReadPostIdUsesWatermarkWithoutThreadMarker(): void {
		$this->readMarkerMapper->method('findByUserAndThread')->willThrowException(new DoesNotExistException(''));
		$this->readMarkerMapper->method('findCategoryMarkersByUserId')->willReturn([$this->createCategoryMarker(10, 2000)]);
		$this->postMapper->method('findLastIdCreatedUpTo')->willReturn(80);

		$this->assertSame(80, $this->service->getLastReadPostId('user1', 1, 10));
	}

	public function testGetLastReadPostIdIgnoresWatermarksOfOtherCategories(): void {
		$this->readMarkerMapper->method('findByUserAndThread')->willThrowException(new DoesNotExistException(''));
		$this->readMarkerMapper->method('findCategoryMarkersByUserId')->willReturn([$this->createCategoryMarker(11, 2000)]);
		$this->postMapper->expects($this->never())->method('findLastIdCreatedUpTo');

		$this->assertNull($this->service->getLastReadPostId('user1', 1, 10));
	}

	public function testMarkCategoryAsReadCascadesToAccessibleDescendants(): void {
		$this->permissionService->method('getAccessibleCategories')->with('user1')->willReturn([1, 2, 4]);
		$this->categoryMapper->method('findChildren')->with(1)->willReturn([
			$this->createCategory(2),
			$this->createCategory(3),
			$this->createCategory(4),
		]);

		$marked = [];
		$this->readMarkerMapper->method('createOrUpdateCategoryMarker')
			->willReturnCallback(function (string $userId, int $categoryId) use (&$marked) {
				$marked[] = $categoryId;
				return new ReadMarker();
			});

		$result = $this->service->markCategoryAsRead('user1', 1);

		$this->assertSame([1, 2, 4], $result);
		$this->assertSame([1, 2, 4], $marked);
	}

	public function testMarkCategoryAsReadSkipsInaccessibleCategory(): void {
		$this->permissionService->method('getAccessibleCategories')->willReturn([]);
		$this->categoryMapper->method('findChildren')->willReturn([]);
		$this->readMarkerMapper->expects($this->never())->method('createOrUpdateCategoryMarker');

		$this->assertSame([], $this->service->markCategoryAsRead('user1', 1));
	}

	private function createThread(int $id, int $categoryId, int $createdAt): Thread {
		$thread = new Thread();
		$thread->setId($id);
		$thread->setCategoryId($categoryId);
		$thread->setCreatedAt($createdAt);
		return $thread;
	}

	private function createThreadMarker(int $threadId, int $lastReadPostId): ReadMarker {
		$marker = new ReadMarker();
		$marker->setEntityId($threadId);
		$marker->setMarkerType(ReadMarker::TYPE_THREAD);
		$marker->setLastReadPostId($lastReadPostId);
		return $marker;
	}

	private function createCategoryMarker(int $categoryId, int $readAt): ReadMarker {
		$marker = new ReadMarker();
		$marker->setEntityId($categoryId);
		$marker->setMarkerType(ReadMarker::TYPE_CATEGORY);
		$marker->setReadAt($readAt);
		return $marker;
	}

	private function createCategory(int $id): Category {
		$category = new Category();
		$category->setId($id);
		return $category;
	}
}
