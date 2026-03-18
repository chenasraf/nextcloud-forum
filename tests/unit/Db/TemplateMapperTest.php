<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\Template;
use OCA\Forum\Db\TemplateMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateMapperTest extends TestCase {
	private TemplateMapper $mapper;

	/** @var IDBConnection&MockObject */
	private IDBConnection $db;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->mapper = new TemplateMapper($this->db);
	}

	public function testTemplateEntityHasCorrectConstants(): void {
		$this->assertEquals('threads', Template::VISIBILITY_THREADS);
		$this->assertEquals('replies', Template::VISIBILITY_REPLIES);
		$this->assertEquals('both', Template::VISIBILITY_BOTH);
		$this->assertEquals('neither', Template::VISIBILITY_NEITHER);
	}

	public function testTemplateEntityJsonSerialize(): void {
		$template = new Template();
		$template->setId(1);
		$template->setUserId('user1');
		$template->setName('My Template');
		$template->setContent('[b]Hello[/b]');
		$template->setVisibility(Template::VISIBILITY_BOTH);
		$template->setSortOrder(5);
		$template->setCreatedAt(1234567890);
		$template->setUpdatedAt(1234567899);

		$json = $template->jsonSerialize();

		$this->assertEquals(1, $json['id']);
		$this->assertEquals('user1', $json['userId']);
		$this->assertEquals('My Template', $json['name']);
		$this->assertEquals('[b]Hello[/b]', $json['content']);
		$this->assertEquals('both', $json['visibility']);
		$this->assertEquals(5, $json['sortOrder']);
		$this->assertEquals(1234567890, $json['createdAt']);
		$this->assertEquals(1234567899, $json['updatedAt']);
	}

	public function testTemplateEntitySettersAndGetters(): void {
		$template = new Template();

		$template->setId(5);
		$this->assertEquals(5, $template->getId());

		$template->setUserId('testuser');
		$this->assertEquals('testuser', $template->getUserId());

		$template->setName('Test Template');
		$this->assertEquals('Test Template', $template->getName());

		$template->setContent('[i]Content[/i]');
		$this->assertEquals('[i]Content[/i]', $template->getContent());

		$template->setVisibility('threads');
		$this->assertEquals('threads', $template->getVisibility());

		$template->setSortOrder(10);
		$this->assertEquals(10, $template->getSortOrder());

		$template->setCreatedAt(9999999999);
		$this->assertEquals(9999999999, $template->getCreatedAt());

		$template->setUpdatedAt(9999999998);
		$this->assertEquals(9999999998, $template->getUpdatedAt());
	}

	public function testNewTemplateHasCorrectTypes(): void {
		$template = new Template();

		$template->setId(1);
		$template->setUserId('user1');
		$template->setName('Template');
		$template->setContent('Content');
		$template->setVisibility('both');
		$template->setSortOrder(0);
		$template->setCreatedAt(1234567890);
		$template->setUpdatedAt(1234567899);

		$this->assertIsInt($template->getId());
		$this->assertIsString($template->getUserId());
		$this->assertIsString($template->getName());
		$this->assertIsString($template->getContent());
		$this->assertIsString($template->getVisibility());
		$this->assertIsInt($template->getSortOrder());
		$this->assertIsInt($template->getCreatedAt());
		$this->assertIsInt($template->getUpdatedAt());
	}

	public function testTemplateVisibilityConstantsAreConsistent(): void {
		$template = new Template();

		$template->setVisibility(Template::VISIBILITY_THREADS);
		$this->assertEquals('threads', $template->getVisibility());

		$template->setVisibility(Template::VISIBILITY_REPLIES);
		$this->assertEquals('replies', $template->getVisibility());

		$template->setVisibility(Template::VISIBILITY_BOTH);
		$this->assertEquals('both', $template->getVisibility());

		$template->setVisibility(Template::VISIBILITY_NEITHER);
		$this->assertEquals('neither', $template->getVisibility());
	}

	public function testJsonSerializeContainsAllKeys(): void {
		$template = new Template();
		$template->setId(1);
		$template->setUserId('user1');
		$template->setName('Name');
		$template->setContent('Content');
		$template->setVisibility('both');
		$template->setSortOrder(0);
		$template->setCreatedAt(1000);
		$template->setUpdatedAt(2000);

		$json = $template->jsonSerialize();

		$this->assertArrayHasKey('id', $json);
		$this->assertArrayHasKey('userId', $json);
		$this->assertArrayHasKey('name', $json);
		$this->assertArrayHasKey('content', $json);
		$this->assertArrayHasKey('visibility', $json);
		$this->assertArrayHasKey('sortOrder', $json);
		$this->assertArrayHasKey('createdAt', $json);
		$this->assertArrayHasKey('updatedAt', $json);
	}
}
