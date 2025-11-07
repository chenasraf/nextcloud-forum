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
 */
class ForumUser extends Entity implements JsonSerializable {
	protected $userId;
	protected $postCount;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('postCount', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'postCount' => $this->getPostCount(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
