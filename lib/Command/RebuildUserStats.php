<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Command;

use OCA\Forum\Service\StatsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildUserStats extends Command {
	public function __construct(
		private StatsService $statsService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this->setName('forum:rebuild-user-stats')
			->setDescription('Rebuild user statistics for all users in the system');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<info>Rebuilding user statistics for all users...</info>');

		$result = $this->statsService->rebuildAllUserStats();

		$output->writeln(sprintf('Processed %d users', $result['users']));
		$output->writeln(sprintf('Created %d new forum users', $result['created']));
		$output->writeln(sprintf('Updated %d existing forum users', $result['updated']));
		$output->writeln('<info>User statistics rebuilt successfully!</info>');

		return 0;
	}
}
