<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\PostMapper;
use OCA\Forum\Db\ThreadMapper;
use OCP\IDBConnection;

/**
 * Search service for coordinating forum searches
 */
class SearchService {
	public function __construct(
		private ThreadMapper $threadMapper,
		private PostMapper $postMapper,
		private QueryParser $queryParser,
		private PermissionService $permissionService,
		private IDBConnection $db,
	) {
	}

	/**
	 * Search threads and posts
	 *
	 * @param string $query Search query string
	 * @param string|null $userId User ID for permission filtering (null for guests)
	 * @param bool $searchThreads Include threads in search
	 * @param bool $searchPosts Include posts in search
	 * @param int|null $categoryId Optional category filter
	 * @param int $limit Results limit per type
	 * @param int $offset Results offset per type
	 * @return array{threads: array, posts: array, threadCount: int, postCount: int}
	 */
	public function search(
		string $query,
		?string $userId,
		bool $searchThreads = true,
		bool $searchPosts = true,
		?int $categoryId = null,
		int $limit = 50,
		int $offset = 0,
	): array {
		$query = trim($query);

		// Validate inputs
		if (empty($query)) {
			return ['threads' => [], 'posts' => [], 'threadCount' => 0, 'postCount' => 0];
		}

		if (!$searchThreads && !$searchPosts) {
			return ['threads' => [], 'posts' => [], 'threadCount' => 0, 'postCount' => 0];
		}

		// Get accessible category IDs for the user
		$categoryIds = $this->getAccessibleCategoryIds($userId, $categoryId);

		if (empty($categoryIds)) {
			return ['threads' => [], 'posts' => [], 'threadCount' => 0, 'postCount' => 0];
		}

		$threads = [];
		$posts = [];
		$threadCount = 0;
		$postCount = 0;

		// Search threads (title + first post content)
		if ($searchThreads) {
			// Use a single QueryBuilder for both conditions to avoid parameter name collisions
			$qb = $this->db->getQueryBuilder();

			$titleConditions = $this->queryParser->parse($qb, $query, 't.title');
			$contentConditions = $this->queryParser->parse($qb, $query, 'p.content');

			if ($titleConditions !== null && $contentConditions !== null) {
				$threads = $this->threadMapper->search($qb, $titleConditions, $contentConditions, $categoryIds, $limit, $offset);
				$threadCount = count($threads);
			}
		}

		// Search posts (reply posts only, excluding first posts)
		if ($searchPosts) {
			$qb = $this->db->getQueryBuilder();
			$whereConditions = $this->queryParser->parse($qb, $query, 'p.content');
			if ($whereConditions !== null) {
				$posts = $this->postMapper->search($qb, $whereConditions, $categoryIds, $limit, $offset);
				$postCount = count($posts);
			}
		}

		return [
			'threads' => $threads,
			'posts' => $posts,
			'threadCount' => $threadCount,
			'postCount' => $postCount,
		];
	}

	/**
	 * Get category IDs accessible by the user
	 *
	 * @param string|null $userId User ID (null for guests)
	 * @param int|null $categoryId Optional specific category filter
	 * @return array<int> Array of category IDs
	 */
	private function getAccessibleCategoryIds(?string $userId, ?int $categoryId = null): array {
		if ($categoryId !== null) {
			// Check if user has access to specific category
			if ($this->permissionService->hasCategoryPermission($userId, $categoryId, 'canView')) {
				return [$categoryId];
			}
			return [];
		}

		// Get all categories user has view permission for
		return $this->permissionService->getAccessibleCategories($userId);
	}
}
