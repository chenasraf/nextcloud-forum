<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getPostCount()
 * @method void setPostCount(int $postCount)
 * @method int getThreadCount()
 * @method void setThreadCount(int $threadCount)
 * @method int|null getLastPostAt()
 * @method void setLastPostAt(?int $lastPostAt)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 * @method string|null getSignature()
 * @method void setSignature(?string $signature)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class UserStats extends Entity implements JsonSerializable {
	public $id;
	protected string $userId = '';
	protected int $postCount = 0;
	protected int $threadCount = 0;
	protected ?int $lastPostAt = null;
	protected ?int $deletedAt = null;
	protected ?string $signature = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('postCount', 'integer');
		$this->addType('threadCount', 'integer');
		$this->addType('lastPostAt', 'integer');
		$this->addType('deletedAt', 'integer');
		$this->addType('signature', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'userId' => $this->userId,
			'postCount' => $this->postCount,
			'threadCount' => $this->threadCount,
			'lastPostAt' => $this->lastPostAt,
			'deletedAt' => $this->deletedAt,
			'isDeleted' => $this->deletedAt !== null,
			'signature' => $this->signature,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
