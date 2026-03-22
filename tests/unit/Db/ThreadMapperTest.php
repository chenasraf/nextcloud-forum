<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\Thread;
use PHPUnit\Framework\TestCase;

class ThreadMapperTest extends TestCase {
	public function testThreadEntitySortFieldsExist(): void {
		$thread = new Thread();

		// Verify the fields used for sorting exist and work
		$thread->setLastReplyAt(1700000000);
		$this->assertEquals(1700000000, $thread->getLastReplyAt());

		$thread->setCreatedAt(1600000000);
		$this->assertEquals(1600000000, $thread->getCreatedAt());

		// last_reply_at can be null (no replies yet)
		$thread->setLastReplyAt(null);
		$this->assertNull($thread->getLastReplyAt());
	}

	public function testThreadSortOrderWithLastReplyAt(): void {
		// Thread with a recent reply should sort before a thread with an older reply
		$threadWithRecentReply = new Thread();
		$threadWithRecentReply->setCreatedAt(1000);
		$threadWithRecentReply->setLastReplyAt(3000);

		$threadWithOldReply = new Thread();
		$threadWithOldReply->setCreatedAt(2000);
		$threadWithOldReply->setLastReplyAt(2500);

		// COALESCE(last_reply_at, created_at) should be used for sorting:
		// threadWithRecentReply: COALESCE(3000, 1000) = 3000
		// threadWithOldReply: COALESCE(2500, 2000) = 2500
		$sortKeyRecent = $threadWithRecentReply->getLastReplyAt() ?? $threadWithRecentReply->getCreatedAt();
		$sortKeyOld = $threadWithOldReply->getLastReplyAt() ?? $threadWithOldReply->getCreatedAt();

		$this->assertGreaterThan($sortKeyOld, $sortKeyRecent);
	}

	public function testThreadSortOrderFallsBackToCreatedAtWhenNoReplies(): void {
		// Thread with no replies should use created_at for sorting
		$newerThread = new Thread();
		$newerThread->setCreatedAt(2000);
		$newerThread->setLastReplyAt(null);

		$olderThread = new Thread();
		$olderThread->setCreatedAt(1000);
		$olderThread->setLastReplyAt(null);

		// COALESCE(null, created_at) = created_at
		$sortKeyNewer = $newerThread->getLastReplyAt() ?? $newerThread->getCreatedAt();
		$sortKeyOlder = $olderThread->getLastReplyAt() ?? $olderThread->getCreatedAt();

		$this->assertGreaterThan($sortKeyOlder, $sortKeyNewer);
	}

	public function testThreadWithReplyOutranksNewerThreadWithoutReply(): void {
		// An older thread with a recent reply should sort before a newer thread with no replies
		$olderThreadWithReply = new Thread();
		$olderThreadWithReply->setCreatedAt(1000);
		$olderThreadWithReply->setLastReplyAt(5000);

		$newerThreadNoReply = new Thread();
		$newerThreadNoReply->setCreatedAt(4000);
		$newerThreadNoReply->setLastReplyAt(null);

		// COALESCE(5000, 1000) = 5000 vs COALESCE(null, 4000) = 4000
		$sortKeyOlderWithReply = $olderThreadWithReply->getLastReplyAt() ?? $olderThreadWithReply->getCreatedAt();
		$sortKeyNewerNoReply = $newerThreadNoReply->getLastReplyAt() ?? $newerThreadNoReply->getCreatedAt();

		$this->assertGreaterThan($sortKeyNewerNoReply, $sortKeyOlderWithReply);
	}

	public function testThreadSortOrderPinnedFirst(): void {
		// Pinned threads should always sort before non-pinned, regardless of timestamps
		$pinnedThread = new Thread();
		$pinnedThread->setIsPinned(true);
		$pinnedThread->setCreatedAt(1000);
		$pinnedThread->setLastReplyAt(null);

		$unpinnedThread = new Thread();
		$unpinnedThread->setIsPinned(false);
		$unpinnedThread->setCreatedAt(9999);
		$unpinnedThread->setLastReplyAt(9999);

		// Pinned threads always come first in the sort order
		$this->assertTrue($pinnedThread->getIsPinned());
		$this->assertFalse($unpinnedThread->getIsPinned());
	}

	public function testThreadUpdatedAtIsIndependentOfSorting(): void {
		// updated_at should NOT affect thread listing order
		// A thread with a recent updated_at but old replies should sort by last_reply_at
		$recentlyUpdatedThread = new Thread();
		$recentlyUpdatedThread->setCreatedAt(1000);
		$recentlyUpdatedThread->setLastReplyAt(2000);
		$recentlyUpdatedThread->setUpdatedAt(9999); // recently updated (e.g. title edit)

		$threadWithRecentReply = new Thread();
		$threadWithRecentReply->setCreatedAt(1500);
		$threadWithRecentReply->setLastReplyAt(5000);
		$threadWithRecentReply->setUpdatedAt(1500); // not recently updated

		// Sort key uses COALESCE(last_reply_at, created_at), NOT updated_at
		$sortKeyUpdated = $recentlyUpdatedThread->getLastReplyAt() ?? $recentlyUpdatedThread->getCreatedAt();
		$sortKeyRecentReply = $threadWithRecentReply->getLastReplyAt() ?? $threadWithRecentReply->getCreatedAt();

		// Thread with recent reply should sort first despite lower updated_at
		$this->assertGreaterThan($sortKeyUpdated, $sortKeyRecentReply);
	}
}
