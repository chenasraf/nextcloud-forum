<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PostMapperTest extends TestCase {
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
	}

	/**
	 * @param list<string> $methods
	 * @return PostMapper&MockObject
	 */
	private function mapperMocking(array $methods): PostMapper {
		return $this->getMockBuilder(PostMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods($methods)
			->getMock();
	}

	private function makePost(int $id, ?int $parentPostId = null, ?int $rootReplyId = null, ?int $purgedAt = null): Post {
		$post = new Post();
		$post->setId($id);
		$post->setThreadId(1);
		$post->setParentPostId($parentPostId);
		$post->setRootReplyId($rootReplyId);
		$post->setPurgedAt($purgedAt);
		return $post;
	}

	public function testPurgeRemovesEverythingButThePostsPlaceInTheTree(): void {
		$post = $this->makePost(10, 5, 1);
		$post->setAuthorId('alice');
		$post->setContent('secret');
		$post->setIsEdited(true);
		$post->setEditedAt(1500);
		$post->setCreatedAt(1000);
		$post->setUpdatedAt(1500);
		$post->setDeletedAt(2000);

		$mapper = $this->mapperMocking(['update']);
		$mapper->expects($this->once())->method('update')->with($post)->willReturn($post);

		$before = time();
		$mapper->purge($post);

		$this->assertSame('', $post->getAuthorId());
		$this->assertSame('', $post->getContent());
		$this->assertFalse($post->getIsEdited());
		$this->assertNull($post->getEditedAt());
		$this->assertGreaterThanOrEqual($before, $post->getPurgedAt());
		$this->assertSame($post->getPurgedAt(), $post->getUpdatedAt());
		$this->assertSame($post->getPurgedAt(), $post->getDeletedAt());

		// Kept: the post's place in the tree and its order among siblings
		$this->assertSame(1, $post->getThreadId());
		$this->assertSame(5, $post->getParentPostId());
		$this->assertSame(1, $post->getRootReplyId());
		$this->assertSame(1000, $post->getCreatedAt());
	}

	/**
	 * Tree below top-level reply 1:
	 *   1
	 *   ├─ 2
	 *   │  └─ 3
	 *   └─ 4 (purged)
	 *      └─ 5
	 *
	 * @return list<Post> Descendants of 1, oldest first
	 */
	private function subtree(): array {
		return [
			$this->makePost(2, 1, 1),
			$this->makePost(3, 2, 1),
			$this->makePost(4, 1, 1, 3000),
			$this->makePost(5, 4, 1),
		];
	}

	/**
	 * @param list<Post> $posts
	 * @return list<int>
	 */
	private function ids(array $posts): array {
		return array_map(fn (Post $p) => $p->getId(), $posts);
	}

	public function testFindDescendantsOfTopLevelReplyReturnsWholeSubtreeInOrder(): void {
		$mapper = $this->mapperMocking(['findDescendantsByRootIds']);
		$mapper->expects($this->once())
			->method('findDescendantsByRootIds')
			->with([1])
			->willReturn($this->subtree());

		$this->assertSame([2, 3, 4, 5], $this->ids($mapper->findDescendants($this->makePost(1))));
	}

	public function testFindDescendantsOfNestedReplyReturnsOnlyItsBranch(): void {
		$mapper = $this->mapperMocking(['findDescendantsByRootIds']);
		$mapper->expects($this->exactly(2))
			->method('findDescendantsByRootIds')
			->with([1])
			->willReturn($this->subtree());

		$this->assertSame([3], $this->ids($mapper->findDescendants($this->makePost(2, 1, 1))));
		$this->assertSame([], $this->ids($mapper->findDescendants($this->makePost(3, 2, 1))));
	}

	public function testFindDescendantsIncludesRepliesBelowPurgedPosts(): void {
		$mapper = $this->mapperMocking(['findDescendantsByRootIds']);
		$mapper->method('findDescendantsByRootIds')->willReturn($this->subtree());

		$this->assertSame([5], $this->ids($mapper->findDescendants($this->makePost(4, 1, 1, 3000))));
	}

	public function testCountDescendantsLeavesOutPurgedPostsAndLoadsEachRootOnce(): void {
		$mapper = $this->mapperMocking(['findDescendantsByRootIds']);
		$mapper->expects($this->once())
			->method('findDescendantsByRootIds')
			->with([1, 7])
			->willReturn(array_merge($this->subtree(), [$this->makePost(8, 7, 7)]));

		$counts = $mapper->countDescendants([
			$this->makePost(1),
			$this->makePost(2, 1, 1),
			$this->makePost(4, 1, 1),
			$this->makePost(7),
			$this->makePost(8, 7, 7),
		]);

		$this->assertSame([1 => 3, 2 => 1, 4 => 1, 7 => 1, 8 => 0], $counts);
	}

	public function testCountDescendantsOfNoPostsSkipsQuery(): void {
		$mapper = $this->mapperMocking(['findDescendantsByRootIds']);
		$mapper->expects($this->never())->method('findDescendantsByRootIds');

		$this->assertSame([], $mapper->countDescendants([]));
	}

	/**
	 * Make every query builder report whether a post has replies from the
	 * given answers, in order
	 *
	 * @param list<bool> $hasReplies
	 */
	private function stubHasReplies(array $hasReplies): void {
		$answers = array_map(fn (bool $has) => $has ? 99 : false, $hasReplies);

		$this->db->method('getQueryBuilder')->willReturnCallback(function () use (&$answers) {
			$qb = $this->createMock(IQueryBuilder::class);
			foreach (['select', 'from', 'where', 'andWhere', 'setMaxResults'] as $method) {
				$qb->method($method)->willReturnSelf();
			}
			$qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
			$qb->method('executeQuery')->willReturnCallback(function () use (&$answers) {
				$result = $this->createMock(IResult::class);
				$result->method('fetchOne')->willReturn(array_shift($answers) ?? false);
				return $result;
			});
			return $qb;
		});
	}

	public function testDeletePurgedAncestorsWithoutRepliesWalksUpUntilAPostHasReplies(): void {
		$this->stubHasReplies([false, true]);

		$mapper = $this->mapperMocking(['findEntities', 'deleteById']);
		$mapper->method('findEntities')->willReturnOnConsecutiveCalls(
			[$this->makePost(5, 3, 1, 3000)],
			[$this->makePost(3, 1, 1, 3000)],
		);
		$mapper->expects($this->once())->method('deleteById')->with(5);

		$mapper->deletePurgedAncestorsWithoutReplies(5);
	}

	public function testDeletePurgedAncestorsWithoutRepliesStopsAtTopLevel(): void {
		$this->stubHasReplies([false, false]);

		$mapper = $this->mapperMocking(['findEntities', 'deleteById']);
		$mapper->method('findEntities')->willReturnOnConsecutiveCalls(
			[$this->makePost(5, 3, 1, 3000)],
			[$this->makePost(3, null, null, 3000)],
		);
		$deleted = [];
		$mapper->method('deleteById')->willReturnCallback(function (int $id) use (&$deleted): int {
			$deleted[] = $id;
			return 1;
		});

		$mapper->deletePurgedAncestorsWithoutReplies(5);

		$this->assertSame([5, 3], $deleted);
	}

	public function testDeletePurgedAncestorsWithoutRepliesKeepsPostsThatAreNotPurged(): void {
		$this->stubHasReplies([]);

		$mapper = $this->mapperMocking(['findEntities', 'deleteById']);
		$mapper->method('findEntities')->willReturn([]);
		$mapper->expects($this->never())->method('deleteById');

		$mapper->deletePurgedAncestorsWithoutReplies(5);
	}

	public function testDeletePurgedAncestorsWithoutRepliesDoesNothingForTopLevelParent(): void {
		$mapper = $this->mapperMocking(['findEntities', 'deleteById']);
		$mapper->expects($this->never())->method('findEntities');
		$mapper->expects($this->never())->method('deleteById');

		$mapper->deletePurgedAncestorsWithoutReplies(null);
	}
}
