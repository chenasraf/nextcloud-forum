<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Cron;

use OCA\Forum\Service\StatsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class RebuildStatsTask extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private StatsService $statsService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run once a week (604800 seconds = 7 days)
		$this->setInterval(604800);
	}

	protected function run($arguments): void {
		$this->logger->info('Starting weekly stats rebuild');

		// Rebuild forum users
		$userResult = $this->statsService->rebuildAllUserStats();
		$this->logger->info('Forum users rebuild completed', [
			'users' => $userResult['users'],
			'created' => $userResult['created'],
			'updated' => $userResult['updated'],
		]);

		// Rebuild category stats
		$categoryResult = $this->statsService->rebuildAllCategoryStats();
		$this->logger->info('Category stats rebuild completed', [
			'categories' => $categoryResult['categories'],
			'updated' => $categoryResult['updated'],
		]);

		// Rebuild thread stats
		$threadResult = $this->statsService->rebuildAllThreadStats();
		$this->logger->info('Thread stats rebuild completed', [
			'threads' => $threadResult['threads'],
			'updated' => $threadResult['updated'],
		]);

		$this->logger->info('Weekly stats rebuild completed');
	}
}
