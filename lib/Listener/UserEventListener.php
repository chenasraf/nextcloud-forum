<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Listener;

use OCA\Forum\Db\UserStatsMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserEventListener implements IEventListener {
	public function __construct(
		private UserStatsMapper $userStatsMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->handleUserDeleted($event);
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
