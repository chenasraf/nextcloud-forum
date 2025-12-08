<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\Bookmark;
use OCA\Forum\Db\BookmarkMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class BookmarkMapperTest extends TestCase {
	private BookmarkMapper $mapper;
	private IDBConnection $db;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->mapper = new BookmarkMapper($this->db);
	}

	public function testBookmarkEntityHasCorrectConstant(): void {
		$this->assertEquals('thread', Bookmark::ENTITY_TYPE_THREAD);
	}

	public function testBookmarkEntityJsonSerialize(): void {
		$bookmark = new Bookmark();
		$bookmark->setId(1);
		$bookmark->setUserId('user1');
		$bookmark->setEntityType(Bookmark::ENTITY_TYPE_THREAD);
		$bookmark->setEntityId(10);
		$bookmark->setCreatedAt(1234567890);

		$json = $bookmark->jsonSerialize();

		$this->assertEquals(1, $json['id']);
		$this->assertEquals('user1', $json['userId']);
		$this->assertEquals('thread', $json['entityType']);
		$this->assertEquals(10, $json['entityId']);
		$this->assertEquals(1234567890, $json['createdAt']);
	}

	public function testBookmarkEntitySettersAndGetters(): void {
		$bookmark = new Bookmark();

		$bookmark->setId(5);
		$this->assertEquals(5, $bookmark->getId());

		$bookmark->setUserId('testuser');
		$this->assertEquals('testuser', $bookmark->getUserId());

		$bookmark->setEntityType('thread');
		$this->assertEquals('thread', $bookmark->getEntityType());

		$bookmark->setEntityId(42);
		$this->assertEquals(42, $bookmark->getEntityId());

		$bookmark->setCreatedAt(9999999999);
		$this->assertEquals(9999999999, $bookmark->getCreatedAt());
	}

	public function testIsThreadBookmarkedCallsIsBookmarkedWithCorrectEntityType(): void {
		// Create a partial mock to test that isThreadBookmarked calls isBookmarked correctly
		$mapper = $this->getMockBuilder(BookmarkMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['isBookmarked'])
			->getMock();

		$mapper->expects($this->once())
			->method('isBookmarked')
			->with('user1', Bookmark::ENTITY_TYPE_THREAD, 10)
			->willReturn(true);

		$result = $mapper->isThreadBookmarked('user1', 10);

		$this->assertTrue($result);
	}

	public function testGetBookmarkedThreadIdsCallsGetBookmarkedEntityIdsWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(BookmarkMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getBookmarkedEntityIds'])
			->getMock();

		$mapper->expects($this->once())
			->method('getBookmarkedEntityIds')
			->with('user1', Bookmark::ENTITY_TYPE_THREAD)
			->willReturn([1, 2, 3]);

		$result = $mapper->getBookmarkedThreadIds('user1');

		$this->assertEquals([1, 2, 3], $result);
	}

	public function testFindThreadBookmarksByUserIdCallsFindByUserIdAndTypeWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(BookmarkMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['findByUserIdAndType'])
			->getMock();

		$expectedBookmarks = [new Bookmark(), new Bookmark()];

		$mapper->expects($this->once())
			->method('findByUserIdAndType')
			->with('user1', Bookmark::ENTITY_TYPE_THREAD, 50, 0)
			->willReturn($expectedBookmarks);

		$result = $mapper->findThreadBookmarksByUserId('user1', 50, 0);

		$this->assertEquals($expectedBookmarks, $result);
	}

	public function testCountThreadBookmarksByUserIdCallsCountByUserIdAndTypeWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(BookmarkMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['countByUserIdAndType'])
			->getMock();

		$mapper->expects($this->once())
			->method('countByUserIdAndType')
			->with('user1', Bookmark::ENTITY_TYPE_THREAD)
			->willReturn(5);

		$result = $mapper->countThreadBookmarksByUserId('user1');

		$this->assertEquals(5, $result);
	}

	public function testBookmarkThreadCallsBookmarkWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(BookmarkMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['bookmark'])
			->getMock();

		$expectedBookmark = new Bookmark();
		$expectedBookmark->setEntityType(Bookmark::ENTITY_TYPE_THREAD);
		$expectedBookmark->setEntityId(10);

		$mapper->expects($this->once())
			->method('bookmark')
			->with('user1', Bookmark::ENTITY_TYPE_THREAD, 10)
			->willReturn($expectedBookmark);

		$result = $mapper->bookmarkThread('user1', 10);

		$this->assertEquals($expectedBookmark, $result);
	}

	public function testUnbookmarkThreadCallsUnbookmarkWithCorrectEntityType(): void {
		$mapper = $this->getMockBuilder(BookmarkMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['unbookmark'])
			->getMock();

		$mapper->expects($this->once())
			->method('unbookmark')
			->with('user1', Bookmark::ENTITY_TYPE_THREAD, 10);

		$mapper->unbookmarkThread('user1', 10);
	}

	public function testBookmarkEntityTypesAreConsistent(): void {
		// Test that entity type constant is used consistently
		$bookmark = new Bookmark();
		$bookmark->setEntityType(Bookmark::ENTITY_TYPE_THREAD);

		$this->assertEquals('thread', $bookmark->getEntityType());
		$this->assertEquals(Bookmark::ENTITY_TYPE_THREAD, $bookmark->getEntityType());
	}

	public function testNewBookmarkHasCorrectTypes(): void {
		$bookmark = new Bookmark();

		// Test that types are properly initialized through addType in constructor
		$bookmark->setId(1);
		$bookmark->setUserId('user1');
		$bookmark->setEntityType('thread');
		$bookmark->setEntityId(10);
		$bookmark->setCreatedAt(1234567890);

		// Verify the values are of the correct types
		$this->assertIsInt($bookmark->getId());
		$this->assertIsString($bookmark->getUserId());
		$this->assertIsString($bookmark->getEntityType());
		$this->assertIsInt($bookmark->getEntityId());
		$this->assertIsInt($bookmark->getCreatedAt());
	}
}
