<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\BBCodeService;
use OCA\Forum\Service\ThreadSummaryService;
use OCA\Forum\Service\UserService;
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ThreadSummaryServiceTest extends TestCase {
	private ThreadSummaryService $service;
	/** @var IManager&MockObject */
	private IManager $manager;
	/** @var AdminSettingsService&MockObject */
	private AdminSettingsService $adminSettingsService;
	/** @var PostMapper&MockObject */
	private PostMapper $postMapper;
	/** @var BBCodeService&MockObject */
	private BBCodeService $bbCodeService;
	/** @var UserService&MockObject */
	private UserService $userService;

	protected function setUp(): void {
		$this->manager = $this->createMock(IManager::class);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(IManager::class)->willReturn($this->manager);
		$this->adminSettingsService = $this->createMock(AdminSettingsService::class);
		$this->postMapper = $this->createMock(PostMapper::class);
		$this->bbCodeService = $this->createMock(BBCodeService::class);
		$this->bbCodeService->method('parse')->willReturnArgument(0);
		$this->userService = $this->createMock(UserService::class);
		$this->userService->method('enrichMultipleUsers')->willReturnCallback(
			fn (array $ids) => array_combine($ids, array_map(fn ($id) => ['displayName' => 'Name of ' . $id], $ids)),
		);

		$this->service = new ThreadSummaryService(
			$container,
			$this->adminSettingsService,
			$this->postMapper,
			$this->createMock(BBCodeMapper::class),
			$this->bbCodeService,
			$this->userService,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testIsAvailableIsFalseWhenDisabledInSettings(): void {
		$this->adminSettingsService->method('getSetting')->willReturn(false);
		$this->manager->expects($this->never())->method('getAvailableTaskTypes');

		$this->assertFalse($this->service->isAvailable('user1'));
	}

	public function testIsAvailableIsTrueWithSummaryProvider(): void {
		$this->adminSettingsService->method('getSetting')->willReturn(true);
		$this->manager->method('getAvailableTaskTypes')->willReturn([TextToTextSummary::ID => []]);

		$this->assertTrue($this->service->isAvailable('user1'));
	}

	public function testIsAvailableIsFalseWithoutSummaryProvider(): void {
		$this->adminSettingsService->method('getSetting')->willReturn(true);
		$this->manager->method('getAvailableTaskTypes')->willReturn(['core:text2text' => []]);

		$this->assertFalse($this->service->isAvailable('user1'));
	}

	public function testBuildInputListsOpeningPostThenRepliesInOrder(): void {
		$this->postMapper->method('findFirstPostByThreadId')->willReturn($this->createPost(1, 'alice', '<p>Opening &amp; question</p>'));
		// Mapper returns replies newest first
		$this->postMapper->method('findNewestReplies')->with(7, 0)->willReturn([
			$this->createPost(3, 'carol', 'Second<br>reply'),
			$this->createPost(2, 'bob', 'First reply'),
		]);

		$text = $this->service->buildInput($this->createThread(), 0);

		$this->assertStringStartsWith("# Thread title\n\n", $text);
		$this->assertStringContainsString("Name of alice (1970-01-01 00:00):\nOpening & question", $text);
		$this->assertStringContainsString("Second\nreply", $text);
		$this->assertStringNotContainsString('<p>', $text);
		$this->assertLessThan(strpos($text, 'First reply'), strpos($text, 'Opening'));
		$this->assertLessThan(strpos($text, 'Second'), strpos($text, 'First reply'));
	}

	public function testBuildInputForUnreadRepliesStartsWithOpeningPost(): void {
		$this->postMapper->method('findFirstPostByThreadId')->willReturn($this->createPost(1, 'alice', 'Opening'));
		$this->postMapper->method('findNewestReplies')->with(7, 5)->willReturn([$this->createPost(6, 'bob', 'Unread reply')]);

		$text = $this->service->buildInput($this->createThread(), 5);

		$this->assertStringContainsString('Unread reply', $text);
		$this->assertLessThan(strpos($text, 'Unread reply'), strpos($text, 'Opening'));
	}

	public function testBuildInputReturnsNullWithoutUnreadReplies(): void {
		$this->postMapper->method('findFirstPostByThreadId')->willReturn($this->createPost(1, 'alice', 'Opening'));
		$this->postMapper->method('findNewestReplies')->willReturn([]);

		$this->assertNull($this->service->buildInput($this->createThread(), 5));
	}

	public function testBuildInputReturnsNullWithoutPosts(): void {
		$this->postMapper->method('findNewestReplies')->willReturn([]);

		$this->assertNull($this->service->buildInput($this->createThread(), 5));
	}

	public function testBuildInputDropsOldestRepliesBeyondLimit(): void {
		$long = str_repeat('x', (int)(ThreadSummaryService::MAX_INPUT_LENGTH / 2));
		$this->postMapper->method('findFirstPostByThreadId')->willReturn($this->createPost(1, 'alice', 'Opening'));
		$this->postMapper->method('findNewestReplies')->willReturn([
			$this->createPost(4, 'bob', 'newest ' . $long),
			$this->createPost(3, 'bob', 'older ' . $long),
			$this->createPost(2, 'bob', 'oldest ' . $long),
		]);

		$text = $this->service->buildInput($this->createThread(), 0);

		$this->assertLessThanOrEqual(ThreadSummaryService::MAX_INPUT_LENGTH, mb_strlen($text));
		$this->assertStringContainsString('Opening', $text);
		$this->assertStringContainsString('newest', $text);
		$this->assertStringNotContainsString('oldest', $text);
	}

	public function testScheduleReusesEarlierTaskForSameContent(): void {
		$this->enableSummaries();
		$this->postMapper->method('findNewestReplies')->willReturn([$this->createPost(6, 'bob', 'Reply')]);
		$existing = $this->createTask(Application::APP_ID, 'thread-summary:7:abc', Task::STATUS_SUCCESSFUL);
		$this->manager->method('getUserTasksByApp')
			->with('user1', Application::APP_ID, $this->stringStartsWith('thread-summary:7:'))
			->willReturn([$existing]);
		$this->manager->expects($this->never())->method('scheduleTask');

		$this->assertSame($existing, $this->service->schedule('user1', $this->createThread(), 5));
	}

	public function testScheduleCreatesTaskWhenEarlierOneFailed(): void {
		$this->enableSummaries();
		$this->postMapper->method('findNewestReplies')->willReturn([$this->createPost(6, 'bob', 'Reply')]);
		$this->manager->method('getUserTasksByApp')->willReturn([
			$this->createTask(Application::APP_ID, 'thread-summary:7:abc', Task::STATUS_FAILED),
		]);
		$this->manager->expects($this->once())->method('scheduleTask')->with($this->callback(
			fn (Task $task) => $task->getTaskTypeId() === TextToTextSummary::ID
				&& $task->getUserId() === 'user1'
				&& $task->getAppId() === Application::APP_ID
				&& str_starts_with((string)$task->getCustomId(), 'thread-summary:7:')
				&& str_contains($task->getInput()['input'], 'Reply'),
		));

		$task = $this->service->schedule('user1', $this->createThread(), 5);

		$this->assertNotNull($task);
	}

	public function testScheduleThrowsWhenUnavailable(): void {
		$this->adminSettingsService->method('getSetting')->willReturn(false);

		$this->expectException(\RuntimeException::class);
		$this->service->schedule('user1', $this->createThread());
	}

	public function testGetTaskRejectsTasksOfOtherThreads(): void {
		$this->manager->method('getUserTask')->with(42, 'user1')
			->willReturn($this->createTask(Application::APP_ID, 'thread-summary:8:abc', Task::STATUS_SUCCESSFUL));

		$this->expectException(NotFoundException::class);
		$this->service->getTask('user1', 7, 42);
	}

	public function testGetTaskRejectsTasksOfOtherApps(): void {
		$this->manager->method('getUserTask')
			->willReturn($this->createTask('spreed', 'thread-summary:7:abc', Task::STATUS_SUCCESSFUL));

		$this->expectException(NotFoundException::class);
		$this->service->getTask('user1', 7, 42);
	}

	public function testSerializeTaskReturnsSummaryOfSuccessfulTask(): void {
		$task = $this->createTask(Application::APP_ID, 'thread-summary:7:abc', Task::STATUS_SUCCESSFUL);
		$task->setOutput(['output' => "  The summary.\n"]);

		$this->assertSame(['taskId' => 42, 'status' => 'successful', 'summary' => 'The summary.'], $this->service->serializeTask($task));
	}

	public function testSerializeTaskMapsCancelledToFailed(): void {
		$task = $this->createTask(Application::APP_ID, 'thread-summary:7:abc', Task::STATUS_CANCELLED);

		$this->assertSame(['taskId' => 42, 'status' => 'failed', 'summary' => null], $this->service->serializeTask($task));
	}

	private function enableSummaries(): void {
		$this->adminSettingsService->method('getSetting')->willReturn(true);
		$this->manager->method('getAvailableTaskTypes')->willReturn([TextToTextSummary::ID => []]);
	}

	private function createThread(): Thread {
		$thread = new Thread();
		$thread->setId(7);
		$thread->setTitle('Thread title');
		return $thread;
	}

	private function createPost(int $id, string $authorId, string $content): Post {
		$post = new Post();
		$post->setId($id);
		$post->setAuthorId($authorId);
		$post->setContent($content);
		$post->setCreatedAt(0);
		return $post;
	}

	private function createTask(string $appId, string $customId, int $status): Task {
		$task = new Task(TextToTextSummary::ID, ['input' => 'text'], $appId, 'user1', $customId);
		$task->setId(42);
		$task->setStatus($status);
		return $task;
	}
}
