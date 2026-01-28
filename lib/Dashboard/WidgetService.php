<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Dashboard;

use OCA\Forum\AppInfo\Application;
use OCA\Forum\Db\Category;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\Thread;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\PermissionService;
use OCP\IURLGenerator;

class WidgetService {
	public function __construct(
		private PermissionService $permissionService,
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private CategoryMapper $categoryMapper,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * Get category IDs accessible by the user
	 *
	 * @param string $userId
	 * @return array<int>
	 */
	public function getAccessibleCategoryIds(string $userId): array {
		return $this->permissionService->getAccessibleCategories($userId);
	}

	/**
	 * Get recent activity (threads and replies combined)
	 *
	 * @param string $userId
	 * @param int $limit
	 * @return array<array{type: string, item: Thread|Post, thread: Thread|null, createdAt: int}>
	 */
	public function getRecentActivity(string $userId, int $limit = 7): array {
		$categoryIds = $this->getAccessibleCategoryIds($userId);
		if (empty($categoryIds)) {
			return [];
		}

		// Get recent threads and replies
		$threads = $this->threadMapper->findRecentThreads($categoryIds, $limit);
		$replies = $this->postMapper->findRecentReplies($categoryIds, $limit);

		// Combine and sort by created_at
		$activity = [];

		foreach ($threads as $thread) {
			$activity[] = [
				'type' => 'thread',
				'item' => $thread,
				'thread' => $thread,
				'createdAt' => $thread->getCreatedAt(),
			];
		}

		// Get thread info for replies
		$threadIds = array_unique(array_map(fn ($post) => $post->getThreadId(), $replies));
		$threadMap = [];
		if (!empty($threadIds)) {
			$threadEntities = $this->threadMapper->findByIds($threadIds);
			foreach ($threadEntities as $thread) {
				$threadMap[$thread->getId()] = $thread;
			}
		}

		foreach ($replies as $post) {
			$thread = $threadMap[$post->getThreadId()] ?? null;
			if ($thread !== null) {
				$activity[] = [
					'type' => 'reply',
					'item' => $post,
					'thread' => $thread,
					'createdAt' => $post->getCreatedAt(),
				];
			}
		}

		// Sort by createdAt descending
		usort($activity, fn ($a, $b) => $b['createdAt'] <=> $a['createdAt']);

		return array_slice($activity, 0, $limit);
	}

	/**
	 * Get top categories by thread count
	 *
	 * @param string $userId
	 * @param int $limit
	 * @return array<Category>
	 */
	public function getTopCategories(string $userId, int $limit = 7): array {
		$categoryIds = $this->getAccessibleCategoryIds($userId);
		if (empty($categoryIds)) {
			return [];
		}

		return $this->categoryMapper->findTopByThreadCount($categoryIds, $limit);
	}

	/**
	 * Get top threads by view count
	 *
	 * @param string $userId
	 * @param int $limit
	 * @return array<Thread>
	 */
	public function getTopThreads(string $userId, int $limit = 7): array {
		$categoryIds = $this->getAccessibleCategoryIds($userId);
		if (empty($categoryIds)) {
			return [];
		}

		return $this->threadMapper->findTopByViews($categoryIds, $limit);
	}

	/**
	 * Get URL for forum home
	 */
	public function getForumUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index');
	}

	/**
	 * Get URL for a thread
	 */
	public function getThreadUrl(Thread $thread): string {
		return $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.page.catchAll',
			['path' => 't/' . $thread->getSlug()]
		);
	}

	/**
	 * Get URL for a category
	 */
	public function getCategoryUrl(Category $category): string {
		return $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.page.catchAll',
			['path' => 'c/' . $category->getSlug()]
		);
	}

	/**
	 * Get the forum app icon URL (dark/black icon for widget header - auto-inverted by Nextcloud)
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	/**
	 * Get the thread icon URL for widget items (black icon, inverted by CSS in dark mode)
	 *
	 * @return string
	 */
	public function getThreadIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'thread-dark.svg');
	}

	/**
	 * Get the folder icon URL for category items (black icon, inverted by CSS in dark mode)
	 *
	 * @return string
	 */
	public function getCategoryIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'folder-dark.svg');
	}
}
