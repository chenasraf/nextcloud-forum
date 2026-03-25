<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Controller;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Controller\ServerAdminController;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Service\StatsService;
use OCA\Forum\Service\UserRoleService;
use OCP\IRequest;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ServerAdminControllerTest extends TestCase {
	private ServerAdminController $controller;

	/** @var RoleMapper&MockObject */
	private RoleMapper $roleMapper;
	/** @var UserRoleService&MockObject */
	private UserRoleService $userRoleService;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var StatsService&MockObject */
	private StatsService $statsService;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var IRequest&MockObject */
	private IRequest $request;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->roleMapper = $this->createMock(RoleMapper::class);
		$this->userRoleService = $this->createMock(UserRoleService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->statsService = $this->createMock(StatsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ServerAdminController(
			Application::APP_ID,
			$this->request,
			$this->roleMapper,
			$this->userRoleService,
			$this->userManager,
			$this->statsService,
			$this->logger
		);
	}

	public function testRebuildStatsReturnsResults(): void {
		$this->statsService->expects($this->once())
			->method('rebuildAllUserStats')
			->willReturn(['users' => 10, 'created' => 2, 'updated' => 8]);
		$this->statsService->expects($this->once())
			->method('rebuildAllCategoryStats')
			->willReturn(['categories' => 5, 'updated' => 5]);
		$this->statsService->expects($this->once())
			->method('rebuildAllThreadStats')
			->willReturn(['threads' => 20, 'updated' => 20]);

		$response = $this->controller->rebuildStats();

		$this->assertEquals(200, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['success']);
		$this->assertStringContainsString('Users processed: 10', $data['message']);
		$this->assertStringContainsString('Categories processed: 5', $data['message']);
		$this->assertStringContainsString('Threads processed: 20', $data['message']);
	}

	public function testRebuildStatsHandlesException(): void {
		$this->statsService->expects($this->once())
			->method('rebuildAllUserStats')
			->willThrowException(new \Exception('DB error'));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Error rebuilding stats'));

		$response = $this->controller->rebuildStats();

		$this->assertEquals(500, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['success']);
	}
}
