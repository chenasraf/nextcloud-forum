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
 * @method int getCategoryId()
 * @method void setCategoryId(int $value)
 * @method string getAuthorId()
 * @method void setAuthorId(string $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getSlug()
 * @method void setSlug(string $value)
 * @method int getViewCount()
 * @method void setViewCount(int $value)
 * @method int getPostCount()
 * @method void setPostCount(int $value)
 * @method int|null getLastPostId()
 * @method void setLastPostId(?int $value)
 * @method bool getIsLocked()
 * @method void setIsLocked(bool $value)
 * @method bool getIsPinned()
 * @method void setIsPinned(bool $value)
 * @method bool getIsHidden()
 * @method void setIsHidden(bool $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $value)
 */
class Thread extends Entity implements JsonSerializable {
	protected $categoryId;
	protected $authorId;
	protected $title;
	protected $slug;
	protected $viewCount;
	protected $postCount;
	protected $lastPostId;
	protected $isLocked;
	protected $isPinned;
	protected $isHidden;
	protected $createdAt;
	protected $updatedAt;
	protected $deletedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('authorId', 'string');
		$this->addType('title', 'string');
		$this->addType('slug', 'string');
		$this->addType('viewCount', 'integer');
		$this->addType('postCount', 'integer');
		$this->addType('lastPostId', 'integer');
		$this->addType('isLocked', 'boolean');
		$this->addType('isPinned', 'boolean');
		$this->addType('isHidden', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'categoryId' => $this->getCategoryId(),
			'authorId' => $this->getAuthorId(),
			'title' => $this->getTitle(),
			'slug' => $this->getSlug(),
			'viewCount' => $this->getViewCount(),
			'postCount' => $this->getPostCount(),
			'lastPostId' => $this->getLastPostId(),
			'isLocked' => $this->getIsLocked(),
			'isPinned' => $this->getIsPinned(),
			'isHidden' => $this->getIsHidden(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
