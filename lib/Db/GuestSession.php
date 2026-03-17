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
 * @method string getSessionToken()
 * @method void setSessionToken(string $value)
 * @method string getDisplayName()
 * @method void setDisplayName(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class GuestSession extends Entity implements JsonSerializable {
	protected $sessionToken;
	protected $displayName;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('sessionToken', 'string');
		$this->addType('displayName', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'sessionToken' => $this->getSessionToken(),
			'displayName' => $this->getDisplayName(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
