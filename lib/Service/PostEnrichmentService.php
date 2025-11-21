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
	) {
	}

	/**
	 * Enrich post content with parsed BBCode, author data, and reactions
	 *
	 * @param mixed $post Post entity or array
	 * @param array $bbcodes Optional pre-loaded BBCode definitions
	 * @param array $reactions Post reactions
	 * @param string|null $currentUserId Current user ID for reaction status
	 * @param array|null $author Optional pre-loaded author data
	 * @return array Enriched post data
	 */
	public function enrichPost(
		mixed $post,
		array $bbcodes = [],
		array $reactions = [],
		?string $currentUserId = null,
		?array $author = null,
	): array {
		if (!is_array($post)) {
			$post = $post->jsonSerialize();
		}

		// Parse BBCode content
		if (empty($bbcodes)) {
			$bbcodes = $this->bbCodeMapper->findAllEnabled();
		}
		$post['content'] = $this->bbCodeService->parse($post['content'], $bbcodes, $post['authorId'], $post['id']);

		// Add author object (includes display name, deleted status, and roles)
		if ($author === null) {
			$post['author'] = $this->userService->enrichUserData($post['authorId']);
		} else {
			$post['author'] = $author;
		}

		// Add reactions (grouped by emoji)
		$post['reactions'] = $this->groupReactions($reactions, $currentUserId);

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

			if ($currentUserId && $userId === $currentUserId) {
				$groups[$emoji]['hasReacted'] = true;
			}
		}

		// Convert to array and sort by count (descending), then alphabetically
		$result = array_values($groups);
		usort($result, function ($a, $b) {
			if ($a['count'] !== $b['count']) {
				return $b['count'] - $a['count'];
			}
			return strcmp($a['emoji'], $b['emoji']);
		});

		return $result;
	}
}
