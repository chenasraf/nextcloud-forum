<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Listener;

use OCA\Forum\Db\UserStatsMapper;
use OCA\Forum\Service\StatsService;
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
		private UserStatsMapper $userStatsMapper,
		private StatsService $statsService,
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
			// Create user stats with zero counts for new user
			$this->statsService->rebuildUserStats($userId);
			$this->logger->info("Created user stats for new Nextcloud user: {$userId}");
		} catch (\Exception $ex) {
			$this->logger->error("Failed to create user stats for new user: {$userId}", [
				'exception' => $ex->getMessage(),
			]);
		}
	}

	private function handleUserDeleted(UserDeletedEvent $event): void {
		$user = $event->getUser();
		$userId = $user->getUID();

		try {
			// Soft delete: mark stats as deleted if they exist
			// Stats only exist if user has posted, so this may not find anything
			$this->userStatsMapper->markDeleted($userId);
			$this->logger->info("Soft-deleted user stats for Nextcloud user: {$userId}");
		} catch (\Exception $ex) {
			// If stats don't exist, that's fine - user never posted
			$this->logger->debug("No user stats found to delete for Nextcloud user: {$userId}");
		}
	}
}
