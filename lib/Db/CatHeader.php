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
 * @method string getName()
 * @method void setName(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 * @method int getSortOrder()
 * @method void setSortOrder(int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class CatHeader extends Entity implements JsonSerializable {
	protected $name;
	protected $description;
	protected $sortOrder;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'description' => $this->getDescription(),
			'sortOrder' => $this->getSortOrder(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
