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
 * @method string getTag()
 * @method void setTag(string $value)
 * @method string getReplacement()
 * @method void setReplacement(string $value)
 * @method string getExample()
 * @method void setExample(string $value)
 * @method string|null getDescription()
 * @method void setDescription(?string $value)
 * @method bool getEnabled()
 * @method void setEnabled(bool $value)
 * @method bool getParseInner()
 * @method void setParseInner(bool $value)
 * @method bool getIsBuiltin()
 * @method void setIsBuiltin(bool $value)
 * @method string|null getSpecialHandler()
 * @method void setSpecialHandler(?string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class BBCode extends Entity implements JsonSerializable {
	protected $tag;
	protected $replacement;
	protected $example;
	protected $description;
	protected $enabled;
	protected $parseInner;
	protected $isBuiltin;
	protected $specialHandler;
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tag', 'string');
		$this->addType('replacement', 'string');
		$this->addType('example', 'string');
		$this->addType('description', 'string');
		$this->addType('enabled', 'boolean');
		$this->addType('parseInner', 'boolean');
		$this->addType('isBuiltin', 'boolean');
		$this->addType('specialHandler', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'tag' => $this->getTag(),
			'replacement' => $this->getReplacement(),
			'example' => $this->getExample(),
			'description' => $this->getDescription(),
			'enabled' => $this->getEnabled(),
			'parseInner' => $this->getParseInner(),
			'isBuiltin' => $this->getIsBuiltin(),
			'specialHandler' => $this->getSpecialHandler(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
