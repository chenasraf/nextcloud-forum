<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ThreadSummaryController;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\ThreadSummaryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ThreadSummaryControllerTest extends TestCase {
	private ThreadSummaryController $controller;
	/** @var ThreadMapper&MockObject */
	private ThreadMapper $threadMapper;
	/** @var ThreadSummaryService&MockObject */
	private ThreadSummaryService $threadSummaryService;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	protected function setUp(): void {
		$this->threadMapper = $this->createMock(ThreadMapper::class);
		$this->threadSummaryService = $this->createMock(ThreadSummaryService::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new ThreadSummaryController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->threadMapper,
			$this->threadSummaryService,
			$this->userSession,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testCreateRequiresSignedInUser(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->threadSummaryService->expects($this->never())->method('schedule');

		$response = $this->controller->create(7);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateFailsWhenSummariesUnavailable(): void {
		$this->signIn();
		$this->threadSummaryService->method('isAvailable')->willReturn(false);
		$this->threadSummaryService->expects($this->never())->method('schedule');

		$response = $this->controller->create(7);

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
	}

	public function testCreateSchedulesSummary(): void {
		$this->signIn();
		$thread = new Thread();
		$this->threadSummaryService->method('isAvailable')->willReturn(true);
		$this->threadMapper->method('find')->with(7)->willReturn($thread);
		$task = new Task(TextToTextSummary::ID, ['input' => 'text'], Application::APP_ID, 'user1');
		$this->threadSummaryService->expects($this->once())->method('schedule')->with('user1', $thread, 12)->willReturn($task);
		$this->threadSummaryService->method('serializeTask')->with($task)
			->willReturn(['taskId' => 42, 'status' => 'scheduled', 'summary' => null]);

		$response = $this->controller->create(7, 12);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['taskId' => 42, 'status' => 'scheduled', 'summary' => null], $response->getData());
	}

	public function testCreateReturnsBadRequestWhenNothingToSummarize(): void {
		$this->signIn();
		$this->threadSummaryService->method('isAvailable')->willReturn(true);
		$this->threadMapper->method('find')->willReturn(new Thread());
		$this->threadSummaryService->method('schedule')->willReturn(null);

		$response = $this->controller->create(7, 12);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateReturnsNotFoundForMissingThread(): void {
		$this->signIn();
		$this->threadSummaryService->method('isAvailable')->willReturn(true);
		$this->threadMapper->method('find')->willThrowException(new DoesNotExistException('missing'));

		$response = $this->controller->create(7);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShowReturnsSummaryState(): void {
		$this->signIn();
		$task = new Task(TextToTextSummary::ID, ['input' => 'text'], Application::APP_ID, 'user1');
		$this->threadSummaryService->method('getTask')->with('user1', 7, 42)->willReturn($task);
		$this->threadSummaryService->method('serializeTask')
			->willReturn(['taskId' => 42, 'status' => 'successful', 'summary' => 'Summary']);

		$response = $this->controller->show(7, 42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Summary', $response->getData()['summary']);
	}

	public function testShowReturnsNotFoundForTasksOfOthers(): void {
		$this->signIn();
		$this->threadSummaryService->method('getTask')->willThrowException(new NotFoundException('nope'));

		$response = $this->controller->show(7, 42);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	private function signIn(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
	}
}
