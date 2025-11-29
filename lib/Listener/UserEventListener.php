<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Listener;

use OCA\Forum\Db\ForumUserMapper;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Service\StatsService;
use OCA\Forum\Service\UserRoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserCreatedEvent|UserDeletedEvent>
 */
class UserEventListener implements IEventListener {
	public function __construct(
		private ForumUserMapper $forumUserMapper,
		private StatsService $statsService,
		private UserRoleService $userRoleService,
		private RoleMapper $roleMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserCreatedEvent) {
			$this->handleUserCreated($event);
		} elseif ($event instanceof UserDeletedEvent) {
			$this->handleUserDeleted($event);
		}
	}

	private function handleUserCreated(UserCreatedEvent $event): void {
		$user = $event->getUser();
		$userId = $user->getUID();

		try {
			// Create forum user record with zero counts for new user
			$this->statsService->rebuildUserStats($userId);
			$this->logger->info("Created forum user record for new Nextcloud user: {$userId}");
		} catch (\Exception $ex) {
			$this->logger->error("Failed to create forum user record for new user: {$userId}", [
				'exception' => $ex->getMessage(),
			]);
		}

		try {
			// Assign default user role to new user
			$defaultRole = $this->roleMapper->findDefaultRole();
			$this->userRoleService->assignRole($userId, $defaultRole->getId(), skipIfExists: true);
			$this->logger->info("Assigned default user role to new Nextcloud user: {$userId}");
		} catch (DoesNotExistException $ex) {
			$this->logger->error("Default role not found, cannot assign role to new user: {$userId}");
		} catch (\Exception $ex) {
			$this->logger->error("Failed to assign default user role to new user: {$userId}", [
				'exception' => $ex->getMessage(),
			]);
		}
	}

	private function handleUserDeleted(UserDeletedEvent $event): void {
		$user = $event->getUser();
		$userId = $user->getUID();

		try {
			// Soft delete: mark forum user as deleted if record exists
			// Record only exists if user has posted, so this may not find anything
			$this->forumUserMapper->markDeleted($userId);
			$this->logger->info("Soft-deleted forum user record for Nextcloud user: {$userId}");
		} catch (\Exception $ex) {
			// If record doesn't exist, that's fine - user never posted
			$this->logger->debug("No forum user record found to delete for Nextcloud user: {$userId}");
		}
	}
}
