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
			'isEdited' => $this->getIsEdited(),
			'isFirstPost' => $this->getIsFirstPost(),
			'editedAt' => $this->getEditedAt(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
