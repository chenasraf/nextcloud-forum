<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\DraftController;
use OCA\Forum\Db\Draft;
use OCA\Forum\Db\DraftMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DraftControllerTest extends TestCase {
	private DraftController $controller;

	/** @var DraftMapper&MockObject */
	private DraftMapper $draftMapper;

	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->draftMapper = $this->createMock(DraftMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new DraftController(
			Application::APP_ID,
			$this->request,
			$this->draftMapper,
			$this->userSession,
			$this->logger
		);
	}

	public function testGetThreadDraftReturnsExistingDraft(): void {
		$categoryId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$draft = $this->createMockDraft(1, $userId, Draft::ENTITY_TYPE_THREAD, $categoryId, 'Test Title', 'Test content');

		$this->draftMapper->expects($this->once())
			->method('findThreadDraft')
			->with($userId, $categoryId)
			->willReturn($draft);

		$response = $this->controller->getThreadDraft($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('draft', $data);
		$this->assertNotNull($data['draft']);
		$this->assertEquals($userId, $data['draft']['userId']);
		$this->assertEquals(Draft::ENTITY_TYPE_THREAD, $data['draft']['entityType']);
		$this->assertEquals($categoryId, $data['draft']['parentId']);
		$this->assertEquals('Test Title', $data['draft']['title']);
		$this->assertEquals('Test content', $data['draft']['content']);
	}

	public function testGetThreadDraftReturnsNullWhenNoDraftExists(): void {
		$categoryId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->draftMapper->expects($this->once())
			->method('findThreadDraft')
			->with($userId, $categoryId)
			->willThrowException(new DoesNotExistException(''));

		$response = $this->controller->getThreadDraft($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('draft', $data);
		$this->assertNull($data['draft']);
	}

	public function testGetThreadDraftReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$categoryId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->getThreadDraft($categoryId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testSaveThreadDraftCreatesNewDraft(): void {
		$categoryId = 1;
		$userId = 'user1';
		$title = 'Test Title';
		$content = 'Test content';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$draft = $this->createMockDraft(1, $userId, Draft::ENTITY_TYPE_THREAD, $categoryId, $title, $content);

		$this->draftMapper->expects($this->once())
			->method('saveThreadDraft')
			->with($userId, $categoryId, $title, $content)
			->willReturn($draft);

		$response = $this->controller->saveThreadDraft($categoryId, $title, $content);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('draft', $data);
		$this->assertEquals($title, $data['draft']['title']);
		$this->assertEquals($content, $data['draft']['content']);
	}

	public function testSaveThreadDraftWithNullTitle(): void {
		$categoryId = 1;
		$userId = 'user1';
		$content = 'Test content';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$draft = $this->createMockDraft(1, $userId, Draft::ENTITY_TYPE_THREAD, $categoryId, null, $content);

		$this->draftMapper->expects($this->once())
			->method('saveThreadDraft')
			->with($userId, $categoryId, null, $content)
			->willReturn($draft);

		$response = $this->controller->saveThreadDraft($categoryId, null, $content);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertNull($data['draft']['title']);
		$this->assertEquals($content, $data['draft']['content']);
	}

	public function testSaveThreadDraftReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$categoryId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->saveThreadDraft($categoryId, 'Title', 'Content');

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testDeleteThreadDraftSuccessfully(): void {
		$categoryId = 1;
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->draftMapper->expects($this->once())
			->method('deleteThreadDraft')
			->with($userId, $categoryId);

		$response = $this->controller->deleteThreadDraft($categoryId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
	}

	public function testDeleteThreadDraftReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$categoryId = 1;

		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->deleteThreadDraft($categoryId);

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	public function testListThreadDraftsSuccessfully(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$draft1 = $this->createMockDraft(1, $userId, Draft::ENTITY_TYPE_THREAD, 1, 'Title 1', 'Content 1');
		$draft2 = $this->createMockDraft(2, $userId, Draft::ENTITY_TYPE_THREAD, 2, 'Title 2', 'Content 2');
		$drafts = [$draft1, $draft2];

		$this->draftMapper->expects($this->once())
			->method('findThreadDrafts')
			->with($userId)
			->willReturn($drafts);

		$response = $this->controller->listThreadDrafts();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('drafts', $data);
		$this->assertCount(2, $data['drafts']);
		$this->assertEquals('Title 1', $data['drafts'][0]['title']);
		$this->assertEquals('Title 2', $data['drafts'][1]['title']);
	}

	public function testListThreadDraftsReturnsEmptyArray(): void {
		$userId = 'user1';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);

		$this->draftMapper->expects($this->once())
			->method('findThreadDrafts')
			->with($userId)
			->willReturn([]);

		$response = $this->controller->listThreadDrafts();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertArrayHasKey('drafts', $data);
		$this->assertCount(0, $data['drafts']);
	}

	public function testListThreadDraftsReturnsUnauthorizedWhenUserNotAuthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->listThreadDrafts();

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertEquals(['error' => 'User not authenticated'], $response->getData());
	}

	private function createMockDraft(
		int $id,
		string $userId,
		string $entityType,
		int $parentId,
		?string $title,
		string $content,
	): Draft {
		$draft = new Draft();
		$draft->setId($id);
		$draft->setUserId($userId);
		$draft->setEntityType($entityType);
		$draft->setParentId($parentId);
		$draft->setTitle($title);
		$draft->setContent($content);
		$draft->setCreatedAt(time());
		$draft->setUpdatedAt(time());
		return $draft;
	}
}
