<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Polymorphic draft entity that supports both thread and post drafts
 *
 * @method int getId()
 * @method void setId(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getEntityType()
 * @method void setEntityType(string $value)
 * @method int getParentId()
 * @method void setParentId(int $value)
 * @method string|null getTitle()
 * @method void setTitle(?string $value)
 * @method string getContent()
 * @method void setContent(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Draft extends Entity implements JsonSerializable {
	public const ENTITY_TYPE_THREAD = 'thread';
	public const ENTITY_TYPE_POST = 'post';

	protected $userId;
	protected $entityType;
	protected $parentId;
	protected $title;
	protected $content;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('entityType', 'string');
		$this->addType('parentId', 'integer');
		$this->addType('title', 'string');
		$this->addType('content', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'entityType' => $this->getEntityType(),
			'parentId' => $this->getParentId(),
			'title' => $this->getTitle(),
			'content' => $this->getContent(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
