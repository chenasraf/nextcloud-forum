<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method int getThreadId()
 * @method void setThreadId(int $value)
 * @method string getAuthorId()
 * @method void setAuthorId(string $value)
 * @method string getContent()
 * @method void setContent(string $value)
 * @method string getSlug()
 * @method void setSlug(string $value)
 * @method bool getIsEdited()
 * @method void setIsEdited(bool $value)
 * @method bool getIsFirstPost()
 * @method void setIsFirstPost(bool $value)
 * @method int|null getEditedAt()
 * @method void setEditedAt(?int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $value)
 */
class Post extends Entity implements JsonSerializable {
	protected $threadId;
	protected $authorId;
	protected $content;
	protected $slug;
	protected $isEdited;
	protected $isFirstPost;
	protected $editedAt;
	protected $createdAt;
	protected $updatedAt;
	protected $deletedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('threadId', 'integer');
		$this->addType('authorId', 'string');
		$this->addType('content', 'string');
		$this->addType('slug', 'string');
		$this->addType('isEdited', 'boolean');
		$this->addType('isFirstPost', 'boolean');
		$this->addType('editedAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'threadId' => $this->getThreadId(),
			'authorId' => $this->getAuthorId(),
			'content' => $this->getContent(),
			'contentRaw' => $this->getContent(),
			'slug' => $this->getSlug(),
			'isEdited' => $this->getIsEdited(),
			'isFirstPost' => $this->getIsFirstPost(),
			'editedAt' => $this->getEditedAt(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}

	public static function enrichPostContent(
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
		$service = \OC::$server->get(\OCA\Forum\Service\BBCodeService::class);
		if (empty($bbcodes)) {
			$mapper = \OC::$server->get(\OCA\Forum\Db\BBCodeMapper::class);
			$bbcodes = $mapper->findAllEnabled();
		}
		$post['content'] = $service->parse($post['content'], $bbcodes, $post['authorId'], $post['id']);

		// Add author object (includes display name, deleted status, and roles)
		if ($author === null) {
			$userService = \OC::$server->get(\OCA\Forum\Service\UserService::class);
			$post['author'] = $userService->enrichUserData($post['authorId']);
		} else {
			$post['author'] = $author;
		}

		// Add reactions (grouped by emoji)
		$post['reactions'] = self::groupReactions($reactions, $currentUserId);

		return $post;
	}

	/**
	 * Group reactions by emoji and calculate counts
	 *
	 * @param array<\OCA\Forum\Db\Reaction> $reactions
	 * @param string|null $currentUserId
	 * @return array<array{emoji: string, count: int, userIds: string[], hasReacted: bool}>
	 */
	private static function groupReactions(array $reactions, ?string $currentUserId): array {
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
