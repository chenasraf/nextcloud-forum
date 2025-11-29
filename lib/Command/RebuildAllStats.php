<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Command;

use OCA\Forum\Service\StatsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildAllStats extends Command {
	/**
	 * RebuildAllStats constructor.
	 */
	public function __construct(
		private StatsService $statsService,
	) {
		parent::__construct();
	}

	/**
	 *
	 */
	protected function configure(): void {
		parent::configure();
		$this->setName('forum:rebuild-all-stats');
		$this->setDescription('Rebuild all statistics including users, categories, threads, and posts');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<info>Starting full stats rebuild...</info>');

		// Rebuild forum users
		$output->writeln('Rebuilding forum users...');
		$userResult = $this->statsService->rebuildAllUserStats();
		$output->writeln(sprintf(
			'  <comment>Users processed: %d, created: %d, updated: %d</comment>',
			$userResult['users'],
			$userResult['created'],
			$userResult['updated']
		));

		// Rebuild category stats
		$output->writeln('Rebuilding category stats...');
		$categoryResult = $this->statsService->rebuildAllCategoryStats();
		$output->writeln(sprintf(
			'  <comment>Categories processed: %d, updated: %d</comment>',
			$categoryResult['categories'],
			$categoryResult['updated']
		));

		// Rebuild thread stats
		$output->writeln('Rebuilding thread stats...');
		$threadResult = $this->statsService->rebuildAllThreadStats();
		$output->writeln(sprintf(
			'  <comment>Threads processed: %d, updated: %d</comment>',
			$threadResult['threads'],
			$threadResult['updated']
		));

		$output->writeln('<info>Full stats rebuild completed!</info>');
		return 0;
	}
}
