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
 * @method int getThreadId()
 * @method void setThreadId(int $value)
 * @method int getLastReadPostId()
 * @method void setLastReadPostId(int $value)
 * @method int getReadAt()
 * @method void setReadAt(int $value)
 */
class ReadMarker extends Entity implements JsonSerializable {
	protected $userId;
	protected $threadId;
	protected $lastReadPostId;
	protected $readAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('threadId', 'integer');
		$this->addType('lastReadPostId', 'integer');
		$this->addType('readAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'threadId' => $this->getThreadId(),
			'lastReadPostId' => $this->getLastReadPostId(),
			'readAt' => $this->getReadAt(),
		];
	}
}
