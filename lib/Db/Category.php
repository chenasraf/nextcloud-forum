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
 * @method int|null getParentId()
 * @method void setParentId(?int $value)
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
 * @method string|null getColor()
 * @method void setColor(?string $value)
 * @method string|null getTextColor()
 * @method void setTextColor(?string $value)
 * @method bool getHideChildrenOnCard()
 * @method void setHideChildrenOnCard(bool $value)
 * @method int|null getAttachmentUploadFolderId()
 * @method void setAttachmentUploadFolderId(?int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Category extends Entity implements JsonSerializable {
	/**
	 * Transient field: the user-specific path of the attachment upload folder,
	 * resolved at response time from $attachmentUploadFolderId via the
	 * requesting user's folder. Not persisted.
	 */
	private ?string $attachmentUploadResolvedPath = null;

	protected $headerId;
	protected $parentId;
	protected $name;
	protected $description;
	protected $slug;
	protected $sortOrder;
	protected $color;
	protected $textColor;
	protected $hideChildrenOnCard;
	protected $attachmentUploadFolderId;
	protected $threadCount;
	protected $postCount;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('headerId', 'integer');
		$this->addType('parentId', 'integer');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('slug', 'string');
		$this->addType('sortOrder', 'integer');
		$this->addType('color', 'string');
		$this->addType('textColor', 'string');
		$this->addType('hideChildrenOnCard', 'boolean');
		$this->addType('attachmentUploadFolderId', 'integer');
		$this->addType('threadCount', 'integer');
		$this->addType('postCount', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'headerId' => $this->getHeaderId(),
			'parentId' => $this->getParentId(),
			'name' => $this->getName(),
			'description' => $this->getDescription(),
			'slug' => $this->getSlug(),
			'sortOrder' => $this->getSortOrder(),
			'color' => $this->getColor(),
			'textColor' => $this->getTextColor(),
			'hideChildrenOnCard' => $this->getHideChildrenOnCard(),
			'attachmentUploadFolderId' => $this->getAttachmentUploadFolderId(),
			'attachmentUploadResolvedPath' => $this->attachmentUploadResolvedPath,
			'threadCount' => $this->getThreadCount(),
			'postCount' => $this->getPostCount(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}

	public function setAttachmentUploadResolvedPath(?string $path): void {
		$this->attachmentUploadResolvedPath = $path;
	}

	public function getAttachmentUploadResolvedPath(): ?string {
		return $this->attachmentUploadResolvedPath;
	}
}
