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
 * @method int|null getEditedAt()
 * @method void setEditedAt(?int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Post extends Entity implements JsonSerializable {
	protected $threadId;
	protected $authorId;
	protected $content;
	protected $slug;
	protected $isEdited;
	protected $editedAt;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('threadId', 'integer');
		$this->addType('authorId', 'string');
		$this->addType('content', 'string');
		$this->addType('slug', 'string');
		$this->addType('isEdited', 'boolean');
		$this->addType('editedAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
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
			'editedAt' => $this->getEditedAt(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}

	public static function enrichPostContent(mixed $post, array $bbcodes = []): array {
		if (!is_array($post)) {
			$post = $post->jsonSerialize();
		}

		// Parse BBCode content
		$service = \OC::$server->get(\OCA\Forum\Service\BBCodeService::class);
		if (empty($bbcodes)) {
			$mapper = \OC::$server->get(\OCA\Forum\Db\BBCodeMapper::class);
			$bbcodes = $mapper->findAllEnabled();
		}
		$post['content'] = $service->parse($post['content'], $bbcodes);

		// Add author display name (obfuscated if user is deleted)
		try {
			$forumUserMapper = \OC::$server->get(\OCA\Forum\Db\ForumUserMapper::class);
			$forumUser = $forumUserMapper->findByUserId($post['authorId']);
			$post['authorDisplayName'] = $forumUser->getDisplayName();
			$post['authorIsDeleted'] = $forumUser->getDeletedAt() !== null;
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			// Forum user doesn't exist, use the original authorId
			$post['authorDisplayName'] = $post['authorId'];
			$post['authorIsDeleted'] = false;
		}

		return $post;
	}
}
