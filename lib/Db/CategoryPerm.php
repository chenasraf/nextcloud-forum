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
 * @method string getTargetType()
 * @method void setTargetType(string $value)
 * @method string getTargetId()
 * @method void setTargetId(string $value)
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
	public const TARGET_TYPE_ROLE = 'role';
	public const TARGET_TYPE_TEAM = 'team';

	protected $categoryId;
	protected $targetType;
	protected $targetId;
	protected $canView;
	protected $canPost;
	protected $canReply;
	protected $canModerate;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('targetType', 'string');
		$this->addType('targetId', 'string');
		$this->addType('canView', 'boolean');
		$this->addType('canPost', 'boolean');
		$this->addType('canReply', 'boolean');
		$this->addType('canModerate', 'boolean');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'categoryId' => $this->getCategoryId(),
			'targetType' => $this->getTargetType(),
			'targetId' => $this->getTargetId(),
			'canView' => $this->getCanView(),
			'canPost' => $this->getCanPost(),
			'canReply' => $this->getCanReply(),
			'canModerate' => $this->getCanModerate(),
		];
	}
}
