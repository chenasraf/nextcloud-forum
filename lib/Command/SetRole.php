<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Command;

use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Service\UserRoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetRole extends Command {
	public function __construct(
		private RoleMapper $roleMapper,
		private UserRoleService $userRoleService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this->setName('forum:set-role')
			->setDescription('Assign a forum role to a user')
			->addArgument('username', InputArgument::REQUIRED, 'The username of the user')
			->addArgument('role', InputArgument::REQUIRED, 'The role ID (numeric) or role name (case-insensitive) to assign');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$username = $input->getArgument('username');
		$roleIdentifier = $input->getArgument('role');

		// Check if user exists
		$user = $this->userManager->get($username);
		if ($user === null) {
			$output->writeln("<error>User '$username' does not exist.</error>");
			return 1;
		}

		// Find role by ID (if numeric) or by name (case insensitive)
		$role = null;
		if (is_numeric($roleIdentifier)) {
			// Try to find by ID
			try {
				$role = $this->roleMapper->find((int)$roleIdentifier);
			} catch (DoesNotExistException $e) {
				$output->writeln("<error>Role with ID '$roleIdentifier' does not exist.</error>");
				return 1;
			}
		} else {
			// Try to find by name (case insensitive)
			try {
				$role = $this->roleMapper->findByNameCaseInsensitive($roleIdentifier);
			} catch (MultipleObjectsReturnedException $e) {
				$output->writeln("<error>Multiple roles found with name '$roleIdentifier'. Please use the role ID instead.</error>");
				return 1;
			} catch (DoesNotExistException $e) {
				$output->writeln("<error>Role '$roleIdentifier' does not exist.</error>");
				return 1;
			}
		}

		// Check if user already has this role
		if ($this->userRoleService->hasRole($username, $role->getId())) {
			$output->writeln("<comment>User '$username' already has the role '{$role->getName()}'.</comment>");
			return 0;
		}

		// Add the role to the user using the service
		try {
			$this->userRoleService->assignRole($username, $role->getId(), skipIfExists: false);
			$output->writeln("<info>Successfully assigned role '{$role->getName()}' to user '$username'.</info>");
			return 0;
		} catch (\Exception $ex) {
			$output->writeln("<error>Failed to assign role '{$role->getName()}' to user '$username': {$ex->getMessage()}</error>");
			return 1;
		}
	}
}
