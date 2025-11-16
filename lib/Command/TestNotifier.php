<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Command;

use OCA\Forum\Notification\Notifier;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestNotifier extends Command {
	public function __construct(
		private INotificationManager $notificationManager,
		private IFactory $l10nFactory,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this->setName('forum:test-notifier')
			->setDescription('Test the forum notification system');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$output->writeln('<info>Testing Forum Notifier...</info>');

			// Instantiate the notifier
			$notifier = new Notifier($this->l10nFactory);

			$output->writeln('✓ Notifier instantiated successfully');
			$output->writeln('  ID: ' . $notifier->getID());
			$output->writeln('  Name: ' . $notifier->getName());

			// Create a test notification (matching production structure)
			$notification = $this->notificationManager->createNotification();

			$notification->setApp('forum')
				->setUser('admin')
				->setDateTime(new \DateTime())
				->setObject('thread', '1')
				->setSubject('new_posts', [
					'threadId' => 1,
					'threadTitle' => 'Test Thread',
					'threadSlug' => 'test-thread',
					'lastPostId' => 1,
					'postCount' => 1,
				])
				->setLink('http://localhost/apps/forum/t/test-thread')
				->setIcon('http://localhost/apps/forum/img/app-dark.svg');

			$output->writeln('✓ Test notification created');

			// Try to prepare it
			$prepared = $notifier->prepare($notification, 'en');

			$output->writeln('✓ Notification prepared successfully');
			$output->writeln('  Subject: ' . $prepared->getParsedSubject());
			$output->writeln('  Link: ' . $prepared->getLink());
			$output->writeln('  Icon: ' . $prepared->getIcon());

			$output->writeln('');
			$output->writeln('<info>All tests passed! The notifier is working correctly.</info>');

			return 0;
		} catch (\Exception $e) {
			$output->writeln('<error>✗ Error: ' . $e->getMessage() . '</error>');
			$output->writeln('<error>Trace:</error>');
			$output->writeln($e->getTraceAsString());

			return 1;
		}
	}
}
