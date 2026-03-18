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
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getName()
 * @method void setName(string $value)
 * @method string getContent()
 * @method void setContent(string $value)
 * @method string getVisibility()
 * @method void setVisibility(string $value)
 * @method int getSortOrder()
 * @method void setSortOrder(int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Template extends Entity implements JsonSerializable {
	public const VISIBILITY_THREADS = 'threads';
	public const VISIBILITY_REPLIES = 'replies';
	public const VISIBILITY_BOTH = 'both';
	public const VISIBILITY_NEITHER = 'neither';

	protected $userId;
	protected $name;
	protected $content;
	protected $visibility;
	protected $sortOrder;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('name', 'string');
		$this->addType('content', 'string');
		$this->addType('visibility', 'string');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'name' => $this->getName(),
			'content' => $this->getContent(),
			'visibility' => $this->getVisibility(),
			'sortOrder' => $this->getSortOrder(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
