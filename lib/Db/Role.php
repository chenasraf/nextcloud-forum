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
 * @method bool getIsSystemRole()
 * @method void setIsSystemRole(bool $value)
 * @method string getRoleType()
 * @method void setRoleType(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Role extends Entity implements JsonSerializable {
	// Role type constants
	public const ROLE_TYPE_ADMIN = 'admin';
	public const ROLE_TYPE_MODERATOR = 'moderator';
	public const ROLE_TYPE_DEFAULT = 'default';
	public const ROLE_TYPE_GUEST = 'guest';
	public const ROLE_TYPE_CUSTOM = 'custom';

	protected $name;
	protected $description;
	protected $colorLight;
	protected $colorDark;
	protected $canAccessAdminTools;
	protected $canEditRoles;
	protected $canEditCategories;
	protected $isSystemRole;
	protected $roleType;
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
		$this->addType('isSystemRole', 'boolean');
		$this->addType('roleType', 'string');
		$this->addType('createdAt', 'integer');
	}

	/**
	 * Check if this role type restricts moderator permissions
	 * Guest and Default roles cannot have moderate permissions
	 *
	 * @return bool True if role cannot have moderator permissions
	 */
	public function isModeratorRestricted(): bool {
		return $this->getRoleType() === self::ROLE_TYPE_GUEST
			|| $this->getRoleType() === self::ROLE_TYPE_DEFAULT;
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
			'isSystemRole' => $this->getIsSystemRole(),
			'roleType' => $this->getRoleType(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
