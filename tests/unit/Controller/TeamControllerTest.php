<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\TeamController;
use OCA\Forum\Db\CategoryPerm;
use OCA\Forum\Db\CategoryPermMapper;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TeamControllerTest extends TestCase {
	private TeamController $controller;
	/** @var CategoryPermMapper&MockObject */
	private CategoryPermMapper $categoryPermMapper;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->categoryPermMapper = $this->createMock(CategoryPermMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new TeamController(
			Application::APP_ID,
			$this->request,
			$this->categoryPermMapper,
			$this->logger
		);
	}

	public function testGetPermissionsReturnsPermissionsForTeam(): void {
		$teamId = 'circle-abc-123';

		$perm1 = new CategoryPerm();
		$perm1->setId(1);
		$perm1->setCategoryId(1);
		$perm1->setTargetType(CategoryPerm::TARGET_TYPE_TEAM);
		$perm1->setTargetId($teamId);
		$perm1->setCanView(true);
		$perm1->setCanPost(true);
		$perm1->setCanReply(true);
		$perm1->setCanModerate(false);

		$perm2 = new CategoryPerm();
		$perm2->setId(2);
		$perm2->setCategoryId(2);
		$perm2->setTargetType(CategoryPerm::TARGET_TYPE_TEAM);
		$perm2->setTargetId($teamId);
		$perm2->setCanView(true);
		$perm2->setCanPost(true);
		$perm2->setCanReply(true);
		$perm2->setCanModerate(true);

		$this->categoryPermMapper->expects($this->once())
			->method('findByTeamId')
			->with($teamId)
			->willReturn([$perm1, $perm2]);

		$response = $this->controller->getPermissions($teamId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(2, $data);
		$this->assertEquals(1, $data[0]['categoryId']);
		$this->assertTrue($data[0]['canView']);
		$this->assertFalse($data[0]['canModerate']);
		$this->assertEquals(2, $data[1]['categoryId']);
		$this->assertTrue($data[1]['canView']);
		$this->assertTrue($data[1]['canModerate']);
	}

	public function testGetPermissionsReturnsEmptyArrayWhenNoPermissions(): void {
		$teamId = 'circle-abc-123';

		$this->categoryPermMapper->expects($this->once())
			->method('findByTeamId')
			->with($teamId)
			->willReturn([]);

		$response = $this->controller->getPermissions($teamId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertIsArray($data);
		$this->assertCount(0, $data);
	}

	public function testGetPermissionsReturnsErrorOnException(): void {
		$teamId = 'circle-abc-123';

		$this->categoryPermMapper->expects($this->once())
			->method('findByTeamId')
			->with($teamId)
			->willThrowException(new \Exception('Database error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->controller->getPermissions($teamId);

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Failed to fetch permissions'], $data);
	}

	public function testGetPermissionsReturnsCorrectTargetType(): void {
		$teamId = 'circle-abc-123';

		$perm = new CategoryPerm();
		$perm->setId(1);
		$perm->setCategoryId(5);
		$perm->setTargetType(CategoryPerm::TARGET_TYPE_TEAM);
		$perm->setTargetId($teamId);
		$perm->setCanView(true);
		$perm->setCanPost(false);
		$perm->setCanReply(false);
		$perm->setCanModerate(false);

		$this->categoryPermMapper->expects($this->once())
			->method('findByTeamId')
			->with($teamId)
			->willReturn([$perm]);

		$response = $this->controller->getPermissions($teamId);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(CategoryPerm::TARGET_TYPE_TEAM, $data[0]['targetType']);
		$this->assertEquals($teamId, $data[0]['targetId']);
	}

	public function testIndexReturnsServiceUnavailableWhenCirclesNotAvailable(): void {
		// The controller's getCirclesManager() checks class_exists and Server::get
		// Since Circles is not available in the test environment, this should return 503
		$response = $this->controller->index();

		$this->assertEquals(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Teams app is not available'], $data);
	}

	public function testUpdatePermissionsReturnsServiceUnavailableWhenCirclesNotAvailable(): void {
		$teamId = 'circle-abc-123';
		$permissions = [
			['categoryId' => 1, 'canView' => true, 'canModerate' => false],
		];

		// Since Circles is not available in the test environment, this should return 503
		$response = $this->controller->updatePermissions($teamId, $permissions);

		$this->assertEquals(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$data = $response->getData();
		$this->assertEquals(['error' => 'Teams app is not available'], $data);
	}

	public function testUpdatePermissionsDoesNotCallMapperWhenCirclesNotAvailable(): void {
		$teamId = 'circle-abc-123';
		$permissions = [
			['categoryId' => 1, 'canView' => true, 'canModerate' => false],
		];

		// Mapper methods should never be called when Circles is unavailable
		$this->categoryPermMapper->expects($this->never())
			->method('deleteByTeamId');
		$this->categoryPermMapper->expects($this->never())
			->method('insert');

		$this->controller->updatePermissions($teamId, $permissions);
	}
}
