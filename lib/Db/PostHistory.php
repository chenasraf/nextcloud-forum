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
 * @method int getPostId()
 * @method void setPostId(int $value)
 * @method string getContent()
 * @method void setContent(string $value)
 * @method string getEditedBy()
 * @method void setEditedBy(string $value)
 * @method int getEditedAt()
 * @method void setEditedAt(int $value)
 */
class PostHistory extends Entity implements JsonSerializable {
	protected $postId;
	protected $content;
	protected $editedBy;
	protected $editedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('postId', 'integer');
		$this->addType('content', 'string');
		$this->addType('editedBy', 'string');
		$this->addType('editedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'postId' => $this->getPostId(),
			'content' => $this->getContent(),
			'editedBy' => $this->getEditedBy(),
			'editedAt' => $this->getEditedAt(),
		];
	}
}
