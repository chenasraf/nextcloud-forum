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
use OCP\IUserManager;
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
		private IUserManager $userManager,
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
	 * Send a test notification to a user
	 * Useful for testing the notification system
	 */
	public function sendTestNotification(string $userId): void {
		$this->createOrUpdateNotification($userId, 1, 1, 'Test Thread', 'test-thread');
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

	/**
	 * Extract mentioned user IDs from content
	 * Supports @username and @"username with spaces" formats
	 *
	 * @param string $content The content to parse
	 * @return array<string> Array of valid user IDs that were mentioned
	 */
	public function extractMentions(string $content): array {
		$mentions = [];

		// Pattern to match @"username with spaces" or @username
		$pattern = '/@(?:"([^"]+)"|([a-zA-Z0-9_.-]+))/';

		if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				// Get the username - either from quoted format or simple format
				$userId = !empty($match[1]) ? $match[1] : $match[2];

				// Verify the user exists
				if ($this->userManager->userExists($userId)) {
					$mentions[] = $userId;
				}
			}
		}

		return array_unique($mentions);
	}

	/**
	 * Notify users who were mentioned in a post
	 *
	 * @param int $postId The post ID
	 * @param int $threadId The thread ID
	 * @param string $authorId The post author's user ID
	 * @param array<string> $mentionedUserIds Array of user IDs to notify
	 */
	public function notifyMentionedUsers(int $postId, int $threadId, string $authorId, array $mentionedUserIds): void {
		if (empty($mentionedUserIds)) {
			return;
		}

		try {
			$thread = $this->threadMapper->find($threadId);
		} catch (\Exception $e) {
			$this->logger->warning('Thread not found for mention notifications', [
				'threadId' => $threadId,
				'error' => $e->getMessage(),
			]);
			return;
		}

		// Get author display name
		$author = $this->userManager->get($authorId);
		$authorDisplayName = $author ? $author->getDisplayName() : $authorId;

		foreach ($mentionedUserIds as $userId) {
			// Don't notify the author if they mention themselves
			if ($userId === $authorId) {
				continue;
			}

			$this->createMentionNotification(
				$userId,
				$postId,
				$threadId,
				$thread->getTitle(),
				$thread->getSlug(),
				$authorId,
				$authorDisplayName
			);
		}
	}

	/**
	 * Handle mention notifications when a post is edited
	 * Sends notifications to newly mentioned users and removes notifications from users no longer mentioned
	 *
	 * @param int $postId The post ID
	 * @param int $threadId The thread ID
	 * @param string $authorId The post author's user ID
	 * @param string $oldContent The content before editing
	 * @param string $newContent The content after editing
	 */
	public function handleMentionChanges(int $postId, int $threadId, string $authorId, string $oldContent, string $newContent): void {
		$oldMentions = $this->extractMentions($oldContent);
		$newMentions = $this->extractMentions($newContent);

		// Find newly added mentions
		$addedMentions = array_diff($newMentions, $oldMentions);

		// Find removed mentions
		$removedMentions = array_diff($oldMentions, $newMentions);

		// Send notifications for new mentions
		if (!empty($addedMentions)) {
			$this->notifyMentionedUsers($postId, $threadId, $authorId, $addedMentions);
		}

		// Remove notifications for removed mentions
		foreach ($removedMentions as $userId) {
			$this->dismissMentionNotification($userId, $postId, $authorId);
		}
	}

	/**
	 * Create a mention notification for a user
	 *
	 * Object ID format: {authorId}_{postId}
	 * This ensures notifications are scoped per author, so if user1 and user2
	 * both mention userX in different posts, each has their own notification.
	 * Dismissing user1's mention won't affect user2's notification.
	 */
	private function createMentionNotification(
		string $userId,
		int $postId,
		int $threadId,
		string $threadTitle,
		string $threadSlug,
		string $authorId,
		string $authorDisplayName,
	): void {
		// Object ID includes author to scope notifications per mentioning user
		$objectId = $authorId . '_' . $postId;

		// Mark any existing mention notification for this author+post as processed
		$existingNotification = $this->notificationManager->createNotification();
		$existingNotification->setApp('forum')
			->setUser($userId)
			->setObject('post_mention', $objectId)
			->setSubject('mention');
		$this->notificationManager->markProcessed($existingNotification);

		// Create new notification
		$notification = $this->notificationManager->createNotification();

		// Generate the thread link (to the specific post)
		$threadLink = $this->urlGenerator->linkToRouteAbsolute('forum.page.index') . 't/' . $threadSlug . '#post-' . $postId;
		$iconPath = $this->urlGenerator->imagePath('forum', 'app-dark.svg');
		$iconUrl = $this->urlGenerator->getAbsoluteURL($iconPath);

		$notification->setApp('forum')
			->setUser($userId)
			->setDateTime(new \DateTime())
			->setObject('post_mention', $objectId)
			->setSubject('mention', [
				'postId' => $postId,
				'threadId' => $threadId,
				'threadTitle' => $threadTitle,
				'threadSlug' => $threadSlug,
				'authorId' => $authorId,
				'authorDisplayName' => $authorDisplayName,
			])
			->setLink($threadLink)
			->setIcon($iconUrl);

		$this->notificationManager->notify($notification);
	}

	/**
	 * Dismiss a mention notification for a specific user and post
	 *
	 * @param string $userId The user who was mentioned (notification recipient)
	 * @param int $postId The post ID containing the mention
	 * @param string $authorId The author who created the mention (for scoping)
	 */
	public function dismissMentionNotification(string $userId, int $postId, string $authorId): void {
		$objectId = $authorId . '_' . $postId;
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('forum')
			->setUser($userId)
			->setObject('post_mention', $objectId);

		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Dismiss all mention notifications for a post (all mentioned users)
	 * Used when a post is deleted
	 *
	 * @param int $postId The post ID
	 * @param string $content The post content to extract mentioned users
	 * @param string $authorId The post author ID (for scoping notifications)
	 */
	public function dismissAllMentionNotifications(int $postId, string $content, string $authorId): void {
		$mentionedUsers = $this->extractMentions($content);

		foreach ($mentionedUsers as $userId) {
			$this->dismissMentionNotification($userId, $postId, $authorId);
		}
	}

	/**
	 * Dismiss all mention notifications for all posts in a thread
	 * Used when a thread is deleted
	 *
	 * @param int $threadId The thread ID
	 */
	public function dismissAllThreadMentionNotifications(int $threadId): void {
		// Get all posts in the thread
		$posts = $this->postMapper->findByThreadId($threadId);

		foreach ($posts as $post) {
			$this->dismissAllMentionNotifications($post->getId(), $post->getContent(), $post->getAuthorId());
		}
	}
}
