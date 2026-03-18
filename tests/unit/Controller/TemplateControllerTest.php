<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\TemplateController;
use OCA\Forum\Db\Template;
use OCA\Forum\Db\TemplateMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateControllerTest extends TestCase {
	private TemplateController $controller;

	/** @var TemplateMapper&MockObject */
	private TemplateMapper $templateMapper;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	/** @var IRequest&MockObject */
	private IRequest $request;

	private string $userId = 'user1';

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->templateMapper = $this->createMock(TemplateMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new TemplateController(
			Application::APP_ID,
			$this->request,
			$this->templateMapper,
			$this->logger,
			$this->userId,
		);
	}

	public function testIndexReturnsTemplates(): void {
		$t1 = $this->createMockTemplate(1, $this->userId, 'Template 1', '[b]Bold[/b]', 'both');
		$t2 = $this->createMockTemplate(2, $this->userId, 'Template 2', '[i]Italic[/i]', 'threads');

		$this->templateMapper->expects($this->once())
			->method('findByUserId')
			->with($this->userId, null)
			->willReturn([$t1, $t2]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(2, $data);
		$this->assertEquals('Template 1', $data[0]['name']);
		$this->assertEquals('Template 2', $data[1]['name']);
	}

	public function testIndexWithVisibilityFilter(): void {
		$t1 = $this->createMockTemplate(1, $this->userId, 'Thread Only', 'content', 'threads');

		$this->templateMapper->expects($this->once())
			->method('findByUserId')
			->with($this->userId, 'threads')
			->willReturn([$t1]);

		$response = $this->controller->index('threads');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(1, $data);
		$this->assertEquals('Thread Only', $data[0]['name']);
	}

	public function testIndexReturnsEmptyArray(): void {
		$this->templateMapper->expects($this->once())
			->method('findByUserId')
			->with($this->userId, null)
			->willReturn([]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(0, $data);
	}

	public function testCreateTemplate(): void {
		$name = 'My Template';
		$content = '[b]Hello[/b]';
		$visibility = 'both';

		$this->templateMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Template $template) use ($name, $content, $visibility) {
				$this->assertEquals($this->userId, $template->getUserId());
				$this->assertEquals($name, $template->getName());
				$this->assertEquals($content, $template->getContent());
				$this->assertEquals($visibility, $template->getVisibility());
				$this->assertEquals(0, $template->getSortOrder());
				$this->assertNotNull($template->getCreatedAt());
				$this->assertNotNull($template->getUpdatedAt());
				$template->setId(1);
				return $template;
			});

		$response = $this->controller->create($name, $content, $visibility);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($name, $data['name']);
		$this->assertEquals($content, $data['content']);
		$this->assertEquals($visibility, $data['visibility']);
	}

	public function testCreateTemplateWithCustomSortOrder(): void {
		$this->templateMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Template $template) {
				$this->assertEquals(5, $template->getSortOrder());
				$template->setId(1);
				return $template;
			});

		$response = $this->controller->create('Name', 'Content', 'both', 5);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testUpdateTemplate(): void {
		$templateId = 1;
		$template = $this->createMockTemplate($templateId, $this->userId, 'Old Name', 'Old Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->with($templateId)
			->willReturn($template);

		$this->templateMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function (Template $t) {
				$this->assertEquals('New Name', $t->getName());
				$this->assertEquals('New Content', $t->getContent());
				$this->assertEquals('threads', $t->getVisibility());
				return $t;
			});

		$response = $this->controller->update($templateId, 'New Name', 'New Content', 'threads');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateTemplatePartial(): void {
		$templateId = 1;
		$template = $this->createMockTemplate($templateId, $this->userId, 'Original', 'Original Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->with($templateId)
			->willReturn($template);

		$this->templateMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function (Template $t) {
				$this->assertEquals('Updated Name', $t->getName());
				// Content and visibility should remain unchanged
				$this->assertEquals('Original Content', $t->getContent());
				$this->assertEquals('both', $t->getVisibility());
				return $t;
			});

		$response = $this->controller->update($templateId, 'Updated Name');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateTemplateNotFound(): void {
		$this->templateMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$response = $this->controller->update(999, 'Name');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdateTemplateForbiddenForOtherUser(): void {
		$templateId = 1;
		$template = $this->createMockTemplate($templateId, 'other_user', 'Name', 'Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->with($templateId)
			->willReturn($template);

		$this->templateMapper->expects($this->never())
			->method('update');

		$response = $this->controller->update($templateId, 'New Name');

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testDestroyTemplate(): void {
		$templateId = 1;
		$template = $this->createMockTemplate($templateId, $this->userId, 'Name', 'Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->with($templateId)
			->willReturn($template);

		$this->templateMapper->expects($this->once())
			->method('delete')
			->with($template);

		$response = $this->controller->destroy($templateId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testDestroyTemplateNotFound(): void {
		$this->templateMapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$response = $this->controller->destroy(999);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testDestroyTemplateForbiddenForOtherUser(): void {
		$templateId = 1;
		$template = $this->createMockTemplate($templateId, 'other_user', 'Name', 'Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->with($templateId)
			->willReturn($template);

		$this->templateMapper->expects($this->never())
			->method('delete');

		$response = $this->controller->destroy($templateId);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testIndexHandlesException(): void {
		$this->templateMapper->expects($this->once())
			->method('findByUserId')
			->willThrowException(new \Exception('DB error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	public function testCreateHandlesException(): void {
		$this->templateMapper->expects($this->once())
			->method('insert')
			->willThrowException(new \Exception('DB error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->controller->create('Name', 'Content');

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	public function testUpdateHandlesException(): void {
		$template = $this->createMockTemplate(1, $this->userId, 'Name', 'Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->willReturn($template);

		$this->templateMapper->expects($this->once())
			->method('update')
			->willThrowException(new \Exception('DB error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->controller->update(1, 'New Name');

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	public function testDestroyHandlesException(): void {
		$template = $this->createMockTemplate(1, $this->userId, 'Name', 'Content', 'both');

		$this->templateMapper->expects($this->once())
			->method('find')
			->willReturn($template);

		$this->templateMapper->expects($this->once())
			->method('delete')
			->willThrowException(new \Exception('DB error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->controller->destroy(1);

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	private function createMockTemplate(
		int $id,
		string $userId,
		string $name,
		string $content,
		string $visibility,
		int $sortOrder = 0,
	): Template {
		$template = new Template();
		$template->setId($id);
		$template->setUserId($userId);
		$template->setName($name);
		$template->setContent($content);
		$template->setVisibility($visibility);
		$template->setSortOrder($sortOrder);
		$template->setCreatedAt(time());
		$template->setUpdatedAt(time());
		return $template;
	}
}
