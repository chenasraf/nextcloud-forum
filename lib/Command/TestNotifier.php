<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Command;

use OCA\Forum\Service\NotificationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestNotifier extends Command {
	public function __construct(
		private NotificationService $notificationService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this->setName('forum:test-notifier')
			->setDescription('Test the forum notification system')
			->addArgument('username', InputArgument::REQUIRED, 'The username to send the test notification to');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$username = $input->getArgument('username');
			$output->writeln('<info>Testing Forum Notifier...</info>');
			$output->writeln('  Target user: ' . $username);

			// Send a test notification using the production notification service
			$this->notificationService->sendTestNotification($username);

			$output->writeln('✓ Test notification sent successfully');
			$output->writeln('  Thread: Test Thread');
			$output->writeln('  Slug: test-thread');
			$output->writeln('');
			$output->writeln('<info>Check the notifications in Nextcloud UI for user: ' . $username . '</info>');

			return 0;
		} catch (\Exception $e) {
			$output->writeln('<error>✗ Error: ' . $e->getMessage() . '</error>');
			$output->writeln('<error>Trace:</error>');
			$output->writeln($e->getTraceAsString());

			return 1;
		}
	}
}
