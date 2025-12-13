<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\Draft;
use OCA\Forum\Db\DraftMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class DraftMapperTest extends TestCase {
	private DraftMapper $mapper;
	private IDBConnection $db;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->mapper = new DraftMapper($this->db);
	}

	public function testDraftEntityHasCorrectConstants(): void {
		$this->assertEquals('thread', Draft::ENTITY_TYPE_THREAD);
		$this->assertEquals('post', Draft::ENTITY_TYPE_POST);
	}

	public function testDraftEntityJsonSerialize(): void {
		$draft = new Draft();
		$draft->setId(1);
		$draft->setUserId('user1');
		$draft->setEntityType(Draft::ENTITY_TYPE_THREAD);
		$draft->setParentId(10);
		$draft->setTitle('Test Title');
		$draft->setContent('Test content');
		$draft->setCreatedAt(1234567890);
		$draft->setUpdatedAt(1234567899);

		$json = $draft->jsonSerialize();

		$this->assertEquals(1, $json['id']);
		$this->assertEquals('user1', $json['userId']);
		$this->assertEquals('thread', $json['entityType']);
		$this->assertEquals(10, $json['parentId']);
		$this->assertEquals('Test Title', $json['title']);
		$this->assertEquals('Test content', $json['content']);
		$this->assertEquals(1234567890, $json['createdAt']);
		$this->assertEquals(1234567899, $json['updatedAt']);
	}

	public function testDraftEntitySettersAndGetters(): void {
		$draft = new Draft();

		$draft->setId(5);
		$this->assertEquals(5, $draft->getId());

		$draft->setUserId('testuser');
		$this->assertEquals('testuser', $draft->getUserId());

		$draft->setEntityType('thread');
		$this->assertEquals('thread', $draft->getEntityType());

		$draft->setParentId(42);
		$this->assertEquals(42, $draft->getParentId());

		$draft->setTitle('My Title');
		$this->assertEquals('My Title', $draft->getTitle());

		$draft->setContent('My content');
		$this->assertEquals('My content', $draft->getContent());

		$draft->setCreatedAt(9999999999);
		$this->assertEquals(9999999999, $draft->getCreatedAt());

		$draft->setUpdatedAt(9999999998);
		$this->assertEquals(9999999998, $draft->getUpdatedAt());
	}

	public function testDraftEntityWithNullTitle(): void {
		$draft = new Draft();
		$draft->setTitle(null);
		$this->assertNull($draft->getTitle());

		$json = $draft->jsonSerialize();
		$this->assertNull($json['title']);
	}

	public function testFindThreadDraftCallsFindByUserAndParentWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(DraftMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['findByUserAndParent'])
			->getMock();

		$expectedDraft = new Draft();
		$expectedDraft->setEntityType(Draft::ENTITY_TYPE_THREAD);

		$mapper->expects($this->once())
			->method('findByUserAndParent')
			->with('user1', Draft::ENTITY_TYPE_THREAD, 10)
			->willReturn($expectedDraft);

		$result = $mapper->findThreadDraft('user1', 10);

		$this->assertEquals($expectedDraft, $result);
	}

	public function testHasThreadDraftCallsHasDraftWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(DraftMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['hasDraft'])
			->getMock();

		$mapper->expects($this->once())
			->method('hasDraft')
			->with('user1', Draft::ENTITY_TYPE_THREAD, 10)
			->willReturn(true);

		$result = $mapper->hasThreadDraft('user1', 10);

		$this->assertTrue($result);
	}

	public function testFindThreadDraftsCallsFindByUserAndTypeWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(DraftMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['findByUserAndType'])
			->getMock();

		$expectedDrafts = [new Draft(), new Draft()];

		$mapper->expects($this->once())
			->method('findByUserAndType')
			->with('user1', Draft::ENTITY_TYPE_THREAD)
			->willReturn($expectedDrafts);

		$result = $mapper->findThreadDrafts('user1');

		$this->assertEquals($expectedDrafts, $result);
	}

	public function testSaveThreadDraftCallsSaveDraftWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(DraftMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['saveDraft'])
			->getMock();

		$expectedDraft = new Draft();
		$expectedDraft->setEntityType(Draft::ENTITY_TYPE_THREAD);
		$expectedDraft->setParentId(10);
		$expectedDraft->setTitle('Test Title');
		$expectedDraft->setContent('Test content');

		$mapper->expects($this->once())
			->method('saveDraft')
			->with('user1', Draft::ENTITY_TYPE_THREAD, 10, 'Test Title', 'Test content')
			->willReturn($expectedDraft);

		$result = $mapper->saveThreadDraft('user1', 10, 'Test Title', 'Test content');

		$this->assertEquals($expectedDraft, $result);
	}

	public function testDeleteThreadDraftCallsDeleteDraftWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(DraftMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['deleteDraft'])
			->getMock();

		$mapper->expects($this->once())
			->method('deleteDraft')
			->with('user1', Draft::ENTITY_TYPE_THREAD, 10);

		$mapper->deleteThreadDraft('user1', 10);
	}

	public function testCountThreadDraftsCallsCountByUserAndTypeWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(DraftMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['countByUserAndType'])
			->getMock();

		$mapper->expects($this->once())
			->method('countByUserAndType')
			->with('user1', Draft::ENTITY_TYPE_THREAD)
			->willReturn(5);

		$result = $mapper->countThreadDrafts('user1');

		$this->assertEquals(5, $result);
	}

	public function testDraftEntityTypesAreConsistent(): void {
		$draft = new Draft();
		$draft->setEntityType(Draft::ENTITY_TYPE_THREAD);

		$this->assertEquals('thread', $draft->getEntityType());
		$this->assertEquals(Draft::ENTITY_TYPE_THREAD, $draft->getEntityType());

		$draft->setEntityType(Draft::ENTITY_TYPE_POST);

		$this->assertEquals('post', $draft->getEntityType());
		$this->assertEquals(Draft::ENTITY_TYPE_POST, $draft->getEntityType());
	}

	public function testNewDraftHasCorrectTypes(): void {
		$draft = new Draft();

		$draft->setId(1);
		$draft->setUserId('user1');
		$draft->setEntityType('thread');
		$draft->setParentId(10);
		$draft->setTitle('Title');
		$draft->setContent('Content');
		$draft->setCreatedAt(1234567890);
		$draft->setUpdatedAt(1234567899);

		$this->assertIsInt($draft->getId());
		$this->assertIsString($draft->getUserId());
		$this->assertIsString($draft->getEntityType());
		$this->assertIsInt($draft->getParentId());
		$this->assertIsString($draft->getTitle());
		$this->assertIsString($draft->getContent());
		$this->assertIsInt($draft->getCreatedAt());
		$this->assertIsInt($draft->getUpdatedAt());
	}
}
