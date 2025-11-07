<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Listener;

use OCA\Forum\Db\ForumUser;
use OCA\Forum\Db\ForumUserMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserCreatedEvent|UserDeletedEvent|UserChangedEvent>
 */
class UserEventListener implements IEventListener {
	public function __construct(
		private ForumUserMapper $forumUserMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserCreatedEvent) {
			$this->handleUserCreated($event);
		} elseif ($event instanceof UserDeletedEvent) {
			$this->handleUserDeleted($event);
		} elseif ($event instanceof UserChangedEvent) {
			$this->handleUserChanged($event);
		}
	}

	private function handleUserCreated(UserCreatedEvent $event): void {
		$user = $event->getUser();
		$userId = $user->getUID();

		try {
			// Check if forum user already exists
			$this->forumUserMapper->findByUserId($userId);
			$this->logger->debug("Forum user already exists for Nextcloud user: {$userId}");
		} catch (DoesNotExistException $e) {
			// Create new forum user
			$forumUser = new ForumUser();
			$forumUser->setUserId($userId);
			$forumUser->setPostCount(0);
			$forumUser->setCreatedAt(time());
			$forumUser->setUpdatedAt(time());

			try {
				$this->forumUserMapper->insert($forumUser);
				$this->logger->info("Created forum user for Nextcloud user: {$userId}");
			} catch (\Exception $ex) {
				$this->logger->error("Failed to create forum user for {$userId}: " . $ex->getMessage());
			}
		}
	}

	private function handleUserDeleted(UserDeletedEvent $event): void {
		$user = $event->getUser();
		$userId = $user->getUID();

		try {
			$forumUser = $this->forumUserMapper->findByUserId($userId);

			// Soft delete: mark as deleted instead of removing the record
			$forumUser->setDeletedAt(time());
			$forumUser->setUpdatedAt(time());

			$this->forumUserMapper->update($forumUser);
			$this->logger->info("Soft-deleted forum user for Nextcloud user: {$userId}");
		} catch (DoesNotExistException $e) {
			// Forum user doesn't exist, nothing to delete
			$this->logger->debug("No forum user found to delete for Nextcloud user: {$userId}");
		} catch (\Exception $ex) {
			$this->logger->error("Failed to soft-delete forum user for {$userId}: " . $ex->getMessage());
		}
	}

	private function handleUserChanged(UserChangedEvent $event): void {
		$user = $event->getUser();
		$userId = $user->getUID();
		$feature = $event->getFeature();
		$value = $event->getValue();

		try {
			$forumUser = $this->forumUserMapper->findByUserId($userId);

			// Update the updatedAt timestamp
			$forumUser->setUpdatedAt(time());

			// You can sync additional user properties here if needed in the future
			// For example, if you add displayName or email fields to ForumUser:
			// if ($feature === 'displayName') {
			//     $forumUser->setDisplayName($value);
			// }

			$this->forumUserMapper->update($forumUser);
			$this->logger->debug("Updated forum user for Nextcloud user: {$userId}, feature: {$feature}");
		} catch (DoesNotExistException $e) {
			// Forum user doesn't exist yet, create it
			$this->logger->debug("Forum user not found during update, creating for: {$userId}");
			$forumUser = new ForumUser();
			$forumUser->setUserId($userId);
			$forumUser->setPostCount(0);
			$forumUser->setCreatedAt(time());
			$forumUser->setUpdatedAt(time());

			try {
				$this->forumUserMapper->insert($forumUser);
				$this->logger->info("Created forum user during update for Nextcloud user: {$userId}");
			} catch (\Exception $ex) {
				$this->logger->error("Failed to create forum user during update for {$userId}: " . $ex->getMessage());
			}
		} catch (\Exception $ex) {
			$this->logger->error("Failed to update forum user for {$userId}: " . $ex->getMessage());
		}
	}
}
