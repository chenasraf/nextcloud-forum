<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Command;

use OCA\Forum\Migration\SeedHelper;
use OCP\Migration\IOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepairSeeds extends Command {
	/**
	 * RepairSeeds constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 *
	 */
	protected function configure(): void {
		parent::configure();
		$this->setName('forum:repair-seeds');
		$this->setDescription('Repair/seed forum data (roles, categories, permissions, BBCodes, user roles, welcome thread)');
		$this->setHelp(
			'This command checks for missing forum data and creates it if needed. '
			. 'It is safe to run multiple times as it will skip data that already exists. '
			. 'Use this to repair incomplete installations or migrations that failed during seeding.'
		);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<info>Starting forum data repair/seed...</info>');
		$output->writeln('This will check and create any missing:');
		$output->writeln('  - Default roles (Admin, Moderator, User)');
		$output->writeln('  - Category headers');
		$output->writeln('  - Default categories');
		$output->writeln('  - Category permissions');
		$output->writeln('  - Default BBCodes');
		$output->writeln('  - User role assignments');
		$output->writeln('  - Welcome thread');
		$output->writeln('');

		try {
			// Create a wrapper to convert Symfony OutputInterface to IOutput
			$migrationOutput = new class($output) implements IOutput {
				private OutputInterface $output;

				public function __construct(OutputInterface $output) {
					$this->output = $output;
				}

				public function info($message): void {
					$this->output->writeln($message);
				}

				public function warning($message): void {
					$this->output->writeln('<comment>' . $message . '</comment>');
				}

				public function startProgress($max = 0): void {
					// Not needed for this command
				}

				public function advance($step = 1, $description = ''): void {
					// Not needed for this command
				}

				public function finishProgress(): void {
					// Not needed for this command
				}
			};

			SeedHelper::seedAll($migrationOutput);

			$output->writeln('');
			$output->writeln('<info>Forum data repair/seed completed successfully!</info>');
			return 0;
		} catch (\Exception $e) {
			$output->writeln('');
			$output->writeln('<error>Forum data repair/seed failed: ' . $e->getMessage() . '</error>');
			$output->writeln('<comment>Check the Nextcloud logs for more details.</comment>');
			return 1;
		}
	}
}
