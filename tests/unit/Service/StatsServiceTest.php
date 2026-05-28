<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Service;

use OCA\Forum\Service\AdminSettingsService;
use OCA\Forum\Service\StatsService;
use OCP\IDBConnection;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StatsServiceTest extends TestCase {
	private StatsService $service;
	/** @var IDBConnection&MockObject */
	private IDBConnection $db;
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	/** @var AdminSettingsService&MockObject */
	private AdminSettingsService $adminSettings;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->adminSettings = $this->createMock(AdminSettingsService::class);

		$this->service = new StatsService(
			$this->db,
			$this->userManager,
			$this->logger,
			$this->adminSettings,
		);
	}

	public function testConstructor(): void {
		$this->assertInstanceOf(StatsService::class, $this->service);
	}
}
