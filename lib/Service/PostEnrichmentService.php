<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\BBCodeMapper;

/**
 * Service for enriching post data with BBCode parsing, author info, and reactions
 */
class PostEnrichmentService {
	public function __construct(
		private BBCodeService $bbCodeService,
		private BBCodeMapper $bbCodeMapper,
		private UserService $userService,
		private EditHistoryVisibilityService $editHistoryVisibilityService,
	) {
	}

	/**
	 * Enrich post content with parsed BBCode, author data, and reactions
	 *
	 * @param \OCA\Forum\Db\Post|array<string, mixed> $post Post entity or array
	 * @param array<\OCA\Forum\Db\BBCode> $bbcodes Optional pre-loaded BBCode definitions
	 * @param array<\OCA\Forum\Db\Reaction> $reactions Post reactions
	 * @param string|null $currentUserId Current user ID for reaction status
	 * @param array|null $author Optional pre-loaded author data
	 * @param int|null $categoryId Category ID for permission checks
	 * @return array Enriched post data
	 */
	public function enrichPost(
		mixed $post,
		array $bbcodes = [],
		array $reactions = [],
		?string $currentUserId = null,
		?array $author = null,
		?int $categoryId = null,
	): array {
		if (!is_array($post)) {
			$post = $post->jsonSerialize();
		}
		/** @var array<string, mixed> $post */

		// Parse BBCode content
		if (empty($bbcodes)) {
			$bbcodes = $this->bbCodeMapper->findAllEnabled();
		}
		$post['content'] = $this->bbCodeService->parse((string)$post['content'], $bbcodes, (string)$post['authorId'], (int)$post['id']);

		// Add author object (includes display name, deleted status, and roles)
		if ($author === null) {
			$post['author'] = $this->userService->enrichUserData((string)$post['authorId']);
		} else {
			$post['author'] = $author;
		}

		// Add reactions (grouped by emoji)
		$post['reactions'] = $this->groupReactions($reactions, $currentUserId);

		// Add canViewHistory flag
		if ($categoryId !== null && !empty($post['isEdited'])) {
			$post['canViewHistory'] = $this->editHistoryVisibilityService->canViewEditHistory(
				$currentUserId,
				(string)$post['authorId'],
				$categoryId,
			);
		} else {
			$post['canViewHistory'] = false;
		}

		return $post;
	}

	/**
	 * Group reactions by emoji and calculate counts
	 *
	 * @param array<\OCA\Forum\Db\Reaction> $reactions
	 * @param string|null $currentUserId
	 * @return array<array{emoji: string, count: int, userIds: string[], hasReacted: bool}>
	 */
	private function groupReactions(array $reactions, ?string $currentUserId): array {
		/** @var array<string, array{emoji: string, count: int, userIds: list<string>, hasReacted: bool}> $groups */
		$groups = [];

		foreach ($reactions as $reaction) {
			$emoji = $reaction->getReactionType();
			$userId = $reaction->getUserId();

			if (!isset($groups[$emoji])) {
				$groups[$emoji] = [
					'emoji' => $emoji,
					'count' => 0,
					'userIds' => [],
					'hasReacted' => false,
				];
			}

			$groups[$emoji]['count']++;
			$groups[$emoji]['userIds'][] = $userId;

			if ($currentUserId !== null && $userId === $currentUserId) {
				$groups[$emoji]['hasReacted'] = true;
			}
		}

		// Convert to array and sort by count (descending), then alphabetically
		$result = array_values($groups);
		usort($result, /**
			 * @param array{emoji: string, count: int, userIds: list<string>, hasReacted: bool} $a
			 * @param array{emoji: string, count: int, userIds: list<string>, hasReacted: bool} $b
			 */
			function (array $a, array $b): int {
				if ($a['count'] !== $b['count']) {
					return $b['count'] - $a['count'];
				}
				return strcmp($a['emoji'], $b['emoji']);
			});

		return $result;
	}
}
