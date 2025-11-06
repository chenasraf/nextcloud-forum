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
 * @method int getPostId()
 * @method void setPostId(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getReactionType()
 * @method void setReactionType(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Reaction extends Entity implements JsonSerializable {
	protected $postId;
	protected $userId;
	protected $reactionType;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('postId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('reactionType', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'post_id' => $this->getPostId(),
			'user_id' => $this->getUserId(),
			'reaction_type' => $this->getReactionType(),
			'created_at' => $this->getCreatedAt(),
		];
	}
}
