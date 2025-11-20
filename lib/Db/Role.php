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
 * @method string|null getColorLight()
 * @method void setColorLight(?string $value)
 * @method string|null getColorDark()
 * @method void setColorDark(?string $value)
 * @method bool getCanAccessAdminTools()
 * @method void setCanAccessAdminTools(bool $value)
 * @method bool getCanEditRoles()
 * @method void setCanEditRoles(bool $value)
 * @method bool getCanEditCategories()
 * @method void setCanEditCategories(bool $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Role extends Entity implements JsonSerializable {
	protected $name;
	protected $description;
	protected $colorLight;
	protected $colorDark;
	protected $canAccessAdminTools;
	protected $canEditRoles;
	protected $canEditCategories;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('colorLight', 'string');
		$this->addType('colorDark', 'string');
		$this->addType('canAccessAdminTools', 'boolean');
		$this->addType('canEditRoles', 'boolean');
		$this->addType('canEditCategories', 'boolean');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'description' => $this->getDescription(),
			'colorLight' => $this->getColorLight(),
			'colorDark' => $this->getColorDark(),
			'canAccessAdminTools' => $this->getCanAccessAdminTools(),
			'canEditRoles' => $this->getCanEditRoles(),
			'canEditCategories' => $this->getCanEditCategories(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
