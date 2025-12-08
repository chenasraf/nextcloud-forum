<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Polymorphic bookmark entity that can reference different entity types
 *
 * @method int getId()
 * @method void setId(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getEntityType()
 * @method void setEntityType(string $value)
 * @method int getEntityId()
 * @method void setEntityId(int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Bookmark extends Entity implements JsonSerializable {
	public const ENTITY_TYPE_THREAD = 'thread';

	protected $userId;
	protected $entityType;
	protected $entityId;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('entityType', 'string');
		$this->addType('entityId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'entityType' => $this->getEntityType(),
			'entityId' => $this->getEntityId(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
