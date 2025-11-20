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
 * @method string getAuthorId()
 * @method void setAuthorId(string $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getSlug()
 * @method void setSlug(string $value)
 * @method int getViewCount()
 * @method void setViewCount(int $value)
 * @method int getPostCount()
 * @method void setPostCount(int $value)
 * @method int|null getLastPostId()
 * @method void setLastPostId(?int $value)
 * @method bool getIsLocked()
 * @method void setIsLocked(bool $value)
 * @method bool getIsPinned()
 * @method void setIsPinned(bool $value)
 * @method bool getIsHidden()
 * @method void setIsHidden(bool $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $value)
 */
class Thread extends Entity implements JsonSerializable {
	protected $categoryId;
	protected $authorId;
	protected $title;
	protected $slug;
	protected $viewCount;
	protected $postCount;
	protected $lastPostId;
	protected $isLocked;
	protected $isPinned;
	protected $isHidden;
	protected $createdAt;
	protected $updatedAt;
	protected $deletedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('categoryId', 'integer');
		$this->addType('authorId', 'string');
		$this->addType('title', 'string');
		$this->addType('slug', 'string');
		$this->addType('viewCount', 'integer');
		$this->addType('postCount', 'integer');
		$this->addType('lastPostId', 'integer');
		$this->addType('isLocked', 'boolean');
		$this->addType('isPinned', 'boolean');
		$this->addType('isHidden', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
		$this->addType('deletedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'categoryId' => $this->getCategoryId(),
			'authorId' => $this->getAuthorId(),
			'title' => $this->getTitle(),
			'slug' => $this->getSlug(),
			'viewCount' => $this->getViewCount(),
			'postCount' => $this->getPostCount(),
			'lastPostId' => $this->getLastPostId(),
			'isLocked' => $this->getIsLocked(),
			'isPinned' => $this->getIsPinned(),
			'isHidden' => $this->getIsHidden(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}

	public static function enrichThread(mixed $thread, ?array $author = null): array {
		if (!is_array($thread)) {
			$thread = $thread->jsonSerialize();
		}

		// Add author object (includes display name, deleted status, and roles)
		if ($author === null) {
			$userService = \OC::$server->get(\OCA\Forum\Service\UserService::class);
			$thread['author'] = $userService->enrichUserData($thread['authorId']);
		} else {
			$thread['author'] = $author;
		}

		// Add category information (slug and name) for navigation
		try {
			$categoryMapper = \OC::$server->get(\OCA\Forum\Db\CategoryMapper::class);
			$category = $categoryMapper->find($thread['categoryId']);
			$thread['categorySlug'] = $category->getSlug();
			$thread['categoryName'] = $category->getName();
		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			// Category doesn't exist
			$thread['categorySlug'] = null;
			$thread['categoryName'] = null;
		}

		// Add subscription status for the current user
		try {
			$userSession = \OC::$server->get(\OCP\IUserSession::class);
			$user = $userSession->getUser();
			if ($user) {
				$subscriptionMapper = \OC::$server->get(\OCA\Forum\Db\ThreadSubscriptionMapper::class);
				$thread['isSubscribed'] = $subscriptionMapper->isUserSubscribed($user->getUID(), $thread['id']);
			} else {
				$thread['isSubscribed'] = false;
			}
		} catch (\Exception $e) {
			// If there's an error checking subscription, default to false
			$thread['isSubscribed'] = false;
		}

		return $thread;
	}
}
