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
 * @method int getCategoryId()
 * @method void setCategoryId(int $value)
 * @method int getRoleId()
 * @method void setRoleId(int $value)
 * @method bool getCanView()
 * @method void setCanView(bool $value)
 * @method bool getCanPost()
 * @method void setCanPost(bool $value)
 * @method bool getCanReply()
 * @method void setCanReply(bool $value)
 * @method bool getCanModerate()
 * @method void setCanModerate(bool $value)
 */
class CategoryPerm extends Entity implements JsonSerializable {
	protected $categoryId;
	protected $roleId;
	protected $canView;
	protected $canPost;
	protected $canReply;
	protected $canModerate;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('roleId', 'integer');
		$this->addType('canView', 'boolean');
		$this->addType('canPost', 'boolean');
		$this->addType('canReply', 'boolean');
		$this->addType('canModerate', 'boolean');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'categoryId' => $this->getCategoryId(),
			'roleId' => $this->getRoleId(),
			'canView' => $this->getCanView(),
			'canPost' => $this->getCanPost(),
			'canReply' => $this->getCanReply(),
			'canModerate' => $this->getCanModerate(),
		];
	}
}
