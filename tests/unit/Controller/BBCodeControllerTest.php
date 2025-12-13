<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\BBCodeController;
use OCA\Forum\Db\BBCode;
use OCA\Forum\Db\BBCodeMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BBCodeControllerTest extends TestCase {
	private BBCodeController $controller;
	/** @var BBCodeMapper&MockObject */
	private BBCodeMapper $bbCodeMapper;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->bbCodeMapper = $this->createMock(BBCodeMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new BBCodeController(
			Application::APP_ID,
			$this->request,
			$this->bbCodeMapper,
			$this->logger
		);
	}

	public function testIndexReturnsAllBBCodesSuccessfully(): void {
		$bbcode1 = $this->createBBCode(1, 'b', '<strong>{content}</strong>', '[b]text[/b]', true, true);
		$bbcode2 = $this->createBBCode(2, 'i', '<em>{content}</em>', '[i]text[/i]', true, true);
		$bbcode3 = $this->createBBCode(3, 'code', '<code>{content}</code>', '[code]text[/code]', false, false);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllNonBuiltin')
			->willReturn([$bbcode1, $bbcode2, $bbcode3]);

		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(3, $data);
	}

	public function testEnabledReturnsOnlyEnabledBBCodes(): void {
		$bbcode1 = $this->createBBCode(1, 'b', '<strong>{content}</strong>', '[b]text[/b]', true, true);
		$bbcode2 = $this->createBBCode(2, 'i', '<em>{content}</em>', '[i]text[/i]', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('findAllEnabled')
			->willReturn([$bbcode1, $bbcode2]);

		$response = $this->controller->enabled();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
	}

	public function testShowReturnsBBCodeSuccessfully(): void {
		$bbcodeId = 1;
		$bbcode = $this->createBBCode($bbcodeId, 'b', '<strong>{content}</strong>', '[b]text[/b]', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willReturn($bbcode);

		$response = $this->controller->show($bbcodeId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($bbcodeId, $data['id']);
		$this->assertEquals('b', $data['tag']);
	}

	public function testShowReturnsNotFoundWhenBBCodeDoesNotExist(): void {
		$bbcodeId = 999;

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willThrowException(new DoesNotExistException('BBCode not found'));

		$response = $this->controller->show($bbcodeId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'BBCode not found'], $response->getData());
	}

	public function testCreateBBCodeSuccessfully(): void {
		$tag = 'url';
		$replacement = '<a href="{url}">{content}</a>';
		$example = '[url=https://example.com]link[/url]';
		$description = 'URL link';
		$enabled = true;

		$createdBBCode = $this->createBBCode(1, $tag, $replacement, $example, $enabled, true);
		$createdBBCode->setDescription($description);

		$this->bbCodeMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($bbcode) use ($createdBBCode) {
				return $createdBBCode;
			});

		$response = $this->controller->create($tag, $replacement, $example, $description, $enabled);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(1, $data['id']);
		$this->assertEquals($tag, $data['tag']);
		$this->assertEquals($replacement, $data['replacement']);
		$this->assertEquals($example, $data['example']);
		$this->assertEquals($description, $data['description']);
		$this->assertTrue($data['enabled']);
	}

	public function testCreateBBCodeWithDefaultValues(): void {
		$tag = 'b';
		$replacement = '<strong>{content}</strong>';
		$example = '[b]text[/b]';

		$createdBBCode = $this->createBBCode(1, $tag, $replacement, $example, true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($bbcode) use ($createdBBCode) {
				return $createdBBCode;
			});

		$response = $this->controller->create($tag, $replacement, $example);

		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($tag, $data['tag']);
		$this->assertEquals($example, $data['example']);
		$this->assertTrue($data['enabled']);
	}

	public function testUpdateBBCodeSuccessfully(): void {
		$bbcodeId = 1;
		$newTag = 'bold';
		$bbcode = $this->createBBCode($bbcodeId, 'b', '<strong>{content}</strong>', '[b]text[/b]', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willReturn($bbcode);

		$this->bbCodeMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedBBCode) use ($newTag) {
				$this->assertEquals($newTag, $updatedBBCode->getTag());
				return $updatedBBCode;
			});

		$response = $this->controller->update($bbcodeId, $newTag);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals($bbcodeId, $data['id']);
	}

	public function testUpdateBBCodeWithMultipleFields(): void {
		$bbcodeId = 1;
		$newTag = 'bold';
		$newReplacement = '<b>{content}</b>';
		$newExample = '[bold]text[/bold]';
		$newDescription = 'Bold text';
		$newEnabled = false;

		$bbcode = $this->createBBCode($bbcodeId, 'b', '<strong>{content}</strong>', '[b]text[/b]', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willReturn($bbcode);

		$this->bbCodeMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($updatedBBCode) use ($newTag, $newReplacement, $newExample, $newDescription, $newEnabled) {
				$this->assertEquals($newTag, $updatedBBCode->getTag());
				$this->assertEquals($newReplacement, $updatedBBCode->getReplacement());
				$this->assertEquals($newExample, $updatedBBCode->getExample());
				$this->assertEquals($newDescription, $updatedBBCode->getDescription());
				$this->assertEquals($newEnabled, $updatedBBCode->getEnabled());
				return $updatedBBCode;
			});

		$response = $this->controller->update($bbcodeId, $newTag, $newReplacement, $newExample, $newDescription, $newEnabled);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateBBCodeReturnsNotFoundWhenBBCodeDoesNotExist(): void {
		$bbcodeId = 999;

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willThrowException(new DoesNotExistException('BBCode not found'));

		$response = $this->controller->update($bbcodeId, 'new-tag');

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'BBCode not found'], $response->getData());
	}

	public function testDestroyBBCodeSuccessfully(): void {
		$bbcodeId = 1;
		$bbcode = $this->createBBCode($bbcodeId, 'b', '<strong>{content}</strong>', '[b]text[/b]', true, true);

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willReturn($bbcode);

		$this->bbCodeMapper->expects($this->once())
			->method('delete')
			->with($bbcode);

		$response = $this->controller->destroy($bbcodeId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['success' => true], $response->getData());
	}

	public function testDestroyBBCodeReturnsNotFoundWhenBBCodeDoesNotExist(): void {
		$bbcodeId = 999;

		$this->bbCodeMapper->expects($this->once())
			->method('find')
			->with($bbcodeId)
			->willThrowException(new DoesNotExistException('BBCode not found'));

		$response = $this->controller->destroy($bbcodeId);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertEquals(['error' => 'BBCode not found'], $response->getData());
	}

	private function createBBCode(int $id, string $tag, string $replacement, string $example, bool $enabled, bool $parseInner): BBCode {
		$bbcode = new BBCode();
		$bbcode->setId($id);
		$bbcode->setTag($tag);
		$bbcode->setReplacement($replacement);
		$bbcode->setExample($example);
		$bbcode->setDescription(null);
		$bbcode->setEnabled($enabled);
		$bbcode->setParseInner($parseInner);
		$bbcode->setCreatedAt(time());
		return $bbcode;
	}
}
