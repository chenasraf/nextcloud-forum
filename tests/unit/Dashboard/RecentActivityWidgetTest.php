<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Dashboard;

use OCA\Forum\Dashboard\RecentActivityWidget;
use OCA\Forum\Dashboard\WidgetService;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\Thread;
use OCA\Forum\Service\UserService;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecentActivityWidgetTest extends TestCase {
	private RecentActivityWidget $widget;

	/** @var IL10N&MockObject */
	private IL10N $l;
	/** @var IURLGenerator&MockObject */
	private IURLGenerator $urlGenerator;
	/** @var WidgetService&MockObject */
	private WidgetService $widgetService;
	/** @var UserService&MockObject */
	private UserService $userService;

	protected function setUp(): void {
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnCallback(function (string $text, array $params = []) {
			foreach ($params as $i => $param) {
				$text = str_replace('%1$s', (string)$param, $text);
			}
			return $text;
		});

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$this->userService = $this->createMock(UserService::class);

		$this->widgetService->method('getThreadUrl')->willReturn('https://example.com/thread');
		$this->widgetService->method('getThreadIconUrl')->willReturn('icon.svg');

		$this->widget = new RecentActivityWidget(
			$this->l,
			$this->urlGenerator,
			$this->widgetService,
			$this->userService,
		);
	}

	private function createMockThread(string $authorId, string $title = 'Test Thread'): Thread {
		$thread = new Thread();
		$thread->setId(1);
		$thread->setAuthorId($authorId);
		$thread->setTitle($title);
		$thread->setSlug('test-thread');
		$thread->setCreatedAt(time());
		return $thread;
	}

	private function createMockPost(string $authorId, int $threadId = 1): Post {
		$post = new Post();
		$post->setId(10);
		$post->setThreadId($threadId);
		$post->setAuthorId($authorId);
		$post->setContent('Test content');
		$post->setCreatedAt(time());
		return $post;
	}

	public function testRegularUserThreadShowsDisplayName(): void {
		$thread = $this->createMockThread('alice');

		$this->widgetService->method('getRecentActivity')->willReturn([
			['type' => 'thread', 'item' => $thread, 'thread' => $thread, 'createdAt' => time()],
		]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with(['alice'])
			->willReturn([
				'alice' => ['displayName' => 'Alice Smith', 'isGuest' => false],
			]);

		$result = $this->widget->getItemsV2('viewer');
		$items = $result->getItems();

		$this->assertCount(1, $items);
		$this->assertEquals('New thread by Alice Smith', $items[0]->getSubtitle());
	}

	public function testGuestThreadShowsDisplayNameWithGuestLabel(): void {
		$guestId = 'guest:abcdef1234567890abcdef1234567890';
		$thread = $this->createMockThread($guestId);

		$this->widgetService->method('getRecentActivity')->willReturn([
			['type' => 'thread', 'item' => $thread, 'thread' => $thread, 'createdAt' => time()],
		]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with([$guestId])
			->willReturn([
				$guestId => ['displayName' => 'BrightMountain42', 'isGuest' => true],
			]);

		$result = $this->widget->getItemsV2('viewer');
		$items = $result->getItems();

		$this->assertCount(1, $items);
		$this->assertEquals('New thread by BrightMountain42 (Guest)', $items[0]->getSubtitle());
	}

	public function testGuestReplyShowsDisplayNameWithGuestLabel(): void {
		$guestId = 'guest:abcdef1234567890abcdef1234567890';
		$thread = $this->createMockThread('alice');
		$post = $this->createMockPost($guestId);

		$this->widgetService->method('getRecentActivity')->willReturn([
			['type' => 'reply', 'item' => $post, 'thread' => $thread, 'createdAt' => time()],
		]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with([$guestId])
			->willReturn([
				$guestId => ['displayName' => 'SwiftRiver99', 'isGuest' => true],
			]);

		$result = $this->widget->getItemsV2('viewer');
		$items = $result->getItems();

		$this->assertCount(1, $items);
		$this->assertEquals('Reply by SwiftRiver99 (Guest)', $items[0]->getSubtitle());
	}

	public function testRegularUserReplyDoesNotAppendGuestLabel(): void {
		$thread = $this->createMockThread('alice');
		$post = $this->createMockPost('bob');

		$this->widgetService->method('getRecentActivity')->willReturn([
			['type' => 'reply', 'item' => $post, 'thread' => $thread, 'createdAt' => time()],
		]);

		$this->userService->expects($this->once())
			->method('enrichMultipleUsers')
			->with(['bob'])
			->willReturn([
				'bob' => ['displayName' => 'Bob Jones', 'isGuest' => false],
			]);

		$result = $this->widget->getItemsV2('viewer');
		$items = $result->getItems();

		$this->assertCount(1, $items);
		$this->assertEquals('Reply by Bob Jones', $items[0]->getSubtitle());
	}
}
