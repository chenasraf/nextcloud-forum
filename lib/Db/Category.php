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
 * @method int getHeaderId()
 * @method void setHeaderId(int $value)
 * @method string getName()
 * @method void setName(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 * @method string getSlug()
 * @method void setSlug(string $value)
 * @method int getSortOrder()
 * @method void setSortOrder(int $value)
 * @method int getThreadCount()
 * @method void setThreadCount(int $value)
 * @method int getPostCount()
 * @method void setPostCount(int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Category extends Entity implements JsonSerializable {
	protected $headerId;
	protected $name;
	protected $description;
	protected $slug;
	protected $sortOrder;
	protected $threadCount;
	protected $postCount;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('headerId', 'integer');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('slug', 'string');
		$this->addType('sortOrder', 'integer');
		$this->addType('threadCount', 'integer');
		$this->addType('postCount', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'headerId' => $this->getHeaderId(),
			'name' => $this->getName(),
			'description' => $this->getDescription(),
			'slug' => $this->getSlug(),
			'sortOrder' => $this->getSortOrder(),
			'threadCount' => $this->getThreadCount(),
			'postCount' => $this->getPostCount(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
