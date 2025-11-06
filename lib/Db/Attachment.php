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
 * @method int getFileid()
 * @method void setFileid(int $value)
 * @method string getFilename()
 * @method void setFilename(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Attachment extends Entity implements JsonSerializable {
	protected $postId;
	protected $fileid;
	protected $filename;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('postId', 'integer');
		$this->addType('fileid', 'integer');
		$this->addType('filename', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'post_id' => $this->getPostId(),
			'fileid' => $this->getFileid(),
			'filename' => $this->getFilename(),
			'created_at' => $this->getCreatedAt(),
		];
	}
}
