<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class NotificationService {
	public function __construct(
		private INotificationManager $notificationManager,
		private ThreadSubscriptionMapper $subscriptionMapper,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private ReadMarkerMapper $readMarkerMapper,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Notify subscribed users when a new post is added to a thread
	 */
	public function notifyThreadSubscribers(int $threadId, int $postId, string $authorId): void {
		// Get all subscribed users for this thread
		$subscriptions = $this->subscriptionMapper->findByThread($threadId);

		// Get thread information
		try {
			$thread = $this->threadMapper->find($threadId);
		} catch (\Exception $e) {
			$this->logger->warning('Thread not found for notifications', [
				'threadId' => $threadId,
				'error' => $e->getMessage(),
			]);
			return;
		}

		foreach ($subscriptions as $subscription) {
			$userId = $subscription->getUserId();

			// Don't notify the author of the post
			if ($userId === $authorId) {
				continue;
			}

			// Create or update notification (collating multiple posts)
			$this->createOrUpdateNotification($userId, $threadId, $postId, $thread->getTitle(), $thread->getSlug());
		}
	}

	/**
	 * Create or update a notification for a user about a thread
	 * This allows collating multiple posts into a single notification
	 */
	private function createOrUpdateNotification(string $userId, int $threadId, int $postId, string $threadTitle, string $threadSlug): void {
		// Calculate the number of unread posts
		$postCount = $this->getUnreadPostCount($userId, $threadId, $postId);

		// Mark existing notifications for this thread/user as processed (to update them)
		$existingNotification = $this->notificationManager->createNotification();
		$existingNotification->setApp('forum')
			->setUser($userId)
			->setObject('thread', (string)$threadId)
			->setSubject('new_posts');
		$this->notificationManager->markProcessed($existingNotification);

		// Create new notification with updated post count
		$notification = $this->notificationManager->createNotification();

		// Generate the thread link and icon
		$threadLink = $this->urlGenerator->linkToRouteAbsolute('forum.page.index') . 't/' . $threadSlug;
		$iconPath = $this->urlGenerator->imagePath('forum', 'app-dark.svg');
		$iconUrl = $this->urlGenerator->getAbsoluteURL($iconPath);

		$notification->setApp('forum')
			->setUser($userId)
			->setDateTime(new \DateTime())
			->setObject('thread', (string)$threadId)
			->setSubject('new_posts', [
				'threadId' => $threadId,
				'threadTitle' => $threadTitle,
				'threadSlug' => $threadSlug,
				'lastPostId' => $postId,
				'postCount' => $postCount,
			])
			->setLink($threadLink)
			->setIcon($iconUrl);

		$this->notificationManager->notify($notification);
	}

	/**
	 * Get the count of unread posts for a user in a thread
	 * Uses an efficient DB COUNT query instead of fetching all posts
	 */
	private function getUnreadPostCount(string $userId, int $threadId, int $latestPostId): int {
		try {
			// Get the user's read marker for this thread
			$readMarker = $this->readMarkerMapper->findByUserAndThread($userId, $threadId);
			$lastReadPostId = $readMarker->getLastReadPostId();

			// Count posts after the last read post using DB query
			$unreadCount = $this->postMapper->countUnreadInThread($threadId, $lastReadPostId);

			return max(1, $unreadCount); // At least 1 (the current post)
		} catch (DoesNotExistException $e) {
			// No read marker, count all posts in the thread
			$count = $this->postMapper->countUnreadInThread($threadId, 0);

			return max(1, $count); // At least 1
		}
	}

	/**
	 * Dismiss notifications for a user when they view a thread
	 */
	public function dismissThreadNotifications(string $userId, int $threadId): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('forum')
			->setUser($userId)
			->setObject('thread', (string)$threadId);

		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Dismiss notifications when read marker catches up
	 */
	public function dismissNotificationsIfRead(string $userId, int $threadId, int $lastReadPostId): void {
		// Get the thread to check the last post
		try {
			$thread = $this->threadMapper->find($threadId);
			$lastPostId = $thread->getLastPostId();

			// If user has read up to or past the last post, dismiss notifications
			if ($lastPostId && $lastReadPostId >= $lastPostId) {
				$this->dismissThreadNotifications($userId, $threadId);
			}
		} catch (\Exception $e) {
			// Thread not found or error, just dismiss anyway
			$this->dismissThreadNotifications($userId, $threadId);
		}
	}
}
