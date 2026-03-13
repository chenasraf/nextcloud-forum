<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Db;

use OCA\Forum\Db\CategoryMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryMapperTest extends TestCase {
	private CategoryMapper $mapper;
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);

		$this->mapper = new CategoryMapper(
			$this->db,
		);
	}

	public function testConstructor(): void {
		$this->assertInstanceOf(CategoryMapper::class, $this->mapper);
	}
}
