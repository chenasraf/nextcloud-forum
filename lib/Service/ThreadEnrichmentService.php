<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\BookmarkMapper;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\ThreadSubscriptionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;

/**
 * Service for enriching thread data with author info, category details, subscription and bookmark status
 */
class ThreadEnrichmentService {
	public function __construct(
		private UserService $userService,
		private CategoryMapper $categoryMapper,
		private IUserSession $userSession,
		private ThreadSubscriptionMapper $threadSubscriptionMapper,
		private BookmarkMapper $bookmarkMapper,
	) {
	}

	/**
	 * Enrich thread data with author, category info, and subscription status
	 *
	 * @param mixed $thread Thread entity or array
	 * @param array|null $author Optional pre-loaded author data
	 * @return array Enriched thread data
	 */
	public function enrichThread(mixed $thread, ?array $author = null): array {
		if (!is_array($thread)) {
			$thread = $thread->jsonSerialize();
		}

		// Add author object (includes display name, deleted status, and roles)
		if ($author === null) {
			$thread['author'] = $this->userService->enrichUserData($thread['authorId']);
		} else {
			$thread['author'] = $author;
		}

		// Add category information (slug and name) for navigation
		try {
			$category = $this->categoryMapper->find($thread['categoryId']);
			$thread['categorySlug'] = $category->getSlug();
			$thread['categoryName'] = $category->getName();
		} catch (DoesNotExistException $e) {
			// Category doesn't exist
			$thread['categorySlug'] = null;
			$thread['categoryName'] = null;
		}

		// Add subscription and bookmark status for the current user
		try {
			$user = $this->userSession->getUser();
			if ($user) {
				$thread['isSubscribed'] = $this->threadSubscriptionMapper->isUserSubscribed($user->getUID(), $thread['id']);
				$thread['isBookmarked'] = $this->bookmarkMapper->isThreadBookmarked($user->getUID(), $thread['id']);
			} else {
				$thread['isSubscribed'] = false;
				$thread['isBookmarked'] = false;
			}
		} catch (\Exception $e) {
			// If there's an error checking subscription/bookmark, default to false
			$thread['isSubscribed'] = false;
			$thread['isBookmarked'] = false;
		}

		return $thread;
	}
}
