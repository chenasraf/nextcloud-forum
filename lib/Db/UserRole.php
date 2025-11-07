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
 * @method int getRoleId()
 * @method void setRoleId(int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class UserRole extends Entity implements JsonSerializable {
	protected $userId;
	protected $roleId;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('roleId', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'roleId' => $this->getRoleId(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
