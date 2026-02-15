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
 * @method int getEntityId()
 * @method void setEntityId(int $value)
 * @method string getMarkerType()
 * @method void setMarkerType(string $value)
 * @method int|null getLastReadPostId()
 * @method void setLastReadPostId(?int $value)
 * @method int getReadAt()
 * @method void setReadAt(int $value)
 */
class ReadMarker extends Entity implements JsonSerializable {
	public const TYPE_THREAD = 'thread';
	public const TYPE_CATEGORY = 'category';

	protected $userId;
	protected $entityId;
	protected $markerType;
	protected $lastReadPostId;
	protected $readAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('entityId', 'integer');
		$this->addType('markerType', 'string');
		$this->addType('lastReadPostId', 'integer');
		$this->addType('readAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'entityId' => $this->getEntityId(),
			'markerType' => $this->getMarkerType(),
			'lastReadPostId' => $this->getLastReadPostId(),
			'readAt' => $this->getReadAt(),
		];
	}
}
