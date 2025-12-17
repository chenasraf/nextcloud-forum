<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Service;

use OCA\Forum\Db\BBCodeMapper;
use OCA\Forum\Db\Post;
use OCA\Forum\Db\PostHistory;
use OCA\Forum\Db\PostHistoryMapper;
use OCA\Forum\Db\PostMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Service for managing post edit history
 */
class PostHistoryService {
	public function __construct(
		private PostHistoryMapper $postHistoryMapper,
		private PostMapper $postMapper,
		private BBCodeService $bbCodeService,
		private BBCodeMapper $bbCodeMapper,
		private UserService $userService,
	) {
	}

	/**
	 * Save the current state of a post before editing
	 *
	 * @param Post $post The post being edited
	 * @param string $editedBy User ID of the person making the edit
	 * @return PostHistory The created history entry
	 */
	public function saveHistory(Post $post, string $editedBy): PostHistory {
		$history = new PostHistory();
		$history->setPostId($post->getId());
		$history->setContent($post->getContent());
		$history->setEditedBy($editedBy);
		// Store when this version was created/last modified (before current edit)
		// Use editedAt if available (previous edit), otherwise use createdAt (original post)
		$history->setEditedAt($post->getEditedAt() ?? $post->getCreatedAt());

		return $this->postHistoryMapper->insert($history);
	}

	/**
	 * Get the complete edit history for a post, including the current version
	 *
	 * @param int $postId Post ID
	 * @return array{current: array, history: array<array>} Current version and historical versions
	 * @throws DoesNotExistException If post not found
	 */
	public function getPostHistory(int $postId): array {
		// Get the current post
		$post = $this->postMapper->find($postId);
		$postData = $post->jsonSerialize();

		// Load BBCodes for parsing
		$bbcodes = $this->bbCodeMapper->findAllEnabled();

		// Parse BBCode content for current version
		$postData['content'] = $this->bbCodeService->parse(
			$postData['content'],
			$bbcodes,
			$postData['authorId'],
			$postData['id']
		);

		// Add author data to current version
		$postData['author'] = $this->userService->enrichUserData($postData['authorId']);

		// Get history entries (ordered by edited_at DESC - newest first)
		$historyEntries = $this->postHistoryMapper->findByPostId($postId);

		// Collect all unique editor IDs for batch lookup
		$editorIds = array_unique(array_map(fn ($h) => $h->getEditedBy(), $historyEntries));
		$editors = $this->userService->enrichMultipleUsers($editorIds);

		// Enrich history entries
		$enrichedHistory = array_map(function ($entry) use ($bbcodes, $postData, $editors) {
			$data = $entry->jsonSerialize();

			// Parse BBCode content for historical version
			$data['content'] = $this->bbCodeService->parse(
				$data['content'],
				$bbcodes,
				$postData['authorId'],
				$postData['id']
			);

			// Add editor info
			$data['editor'] = $editors[$entry->getEditedBy()] ?? null;

			return $data;
		}, $historyEntries);

		return [
			'current' => $postData,
			'history' => $enrichedHistory,
		];
	}

	/**
	 * Check if a post has edit history
	 *
	 * @param int $postId Post ID
	 * @return bool True if there is history
	 */
	public function hasHistory(int $postId): bool {
		return $this->postHistoryMapper->countByPostId($postId) > 0;
	}

	/**
	 * Get the count of history entries for a post
	 *
	 * @param int $postId Post ID
	 * @return int Number of history entries
	 */
	public function getHistoryCount(int $postId): int {
		return $this->postHistoryMapper->countByPostId($postId);
	}
}
