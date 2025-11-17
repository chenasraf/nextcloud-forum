<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Cron;

use OCA\Forum\Service\UserStatsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class RebuildUserStatsTask extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private UserStatsService $userStatsService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run once a week (604800 seconds = 7 days)
		$this->setInterval(604800);
	}

	protected function run($arguments): void {
		$this->logger->info('Starting weekly user stats rebuild');

		$result = $this->userStatsService->rebuildAllUserStats();

		$this->logger->info('User stats rebuild completed', [
			'users' => $result['users'],
			'created' => $result['created'],
			'updated' => $result['updated'],
		]);
	}
}
