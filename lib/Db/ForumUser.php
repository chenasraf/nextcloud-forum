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
 * @method int getPostCount()
 * @method void setPostCount(int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $value)
 */
class ForumUser extends Entity implements JsonSerializable {
	protected $userId;
	protected $postCount;
	protected $createdAt;
	protected $updatedAt;
	protected $deletedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('postCount', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'postCount' => $this->getPostCount(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
			'deletedAt' => $this->getDeletedAt(),
			'isDeleted' => $this->getDeletedAt() !== null,
		];
	}

	/**
	 * Get the display name for this user
	 * Returns obfuscated name if user is deleted
	 *
	 * @return string
	 */
	public function getDisplayName(): string {
		if ($this->getDeletedAt() !== null) {
			// User is deleted, return obfuscated name
			return 'Deleted User #' . $this->getId();
		}

		return $this->getUserId();
	}
}
