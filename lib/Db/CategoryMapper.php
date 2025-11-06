<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Db;

use OCA\Forum\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserSession;

/**
 * @template-extends QBMapper<Category>
 */
class CategoryMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IUserSession $userSession,
		private UserRoleMapper $userRoleMapper,
	) {
		parent::__construct($db, Application::tableName('forum_categories'), Category::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Category {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findBySlug(string $slug): Category {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('slug', $qb->createNamedParameter($slug, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Get role IDs for the current user
	 *
	 * @return array<int>
	 */
	private function getUserRoleIds(): array {
		$user = $this->userSession->getUser();
		if (!$user) {
			return [];
		}

		$userRoles = $this->userRoleMapper->findByUserId($user->getUID());
		return array_map(fn ($ur) => $ur->getRoleId(), $userRoles);
	}

	/**
	 * Filter categories by user permissions
	 *
	 * @param array<Category> $categories
	 * @return array<Category>
	 */
	private function filterByPermissions(array $categories): array {
		if (empty($categories)) {
			return [];
		}

		$categoryIds = array_map(fn ($cat) => $cat->getId(), $categories);
		$userRoleIds = $this->getUserRoleIds();

		// Get all permissions for these categories
		$qb = $this->db->getQueryBuilder();
		$qb->select('category_id', 'role_id', 'can_view')
			->from(Application::tableName('forum_category_perms'))
			->where($qb->expr()->in('category_id', $qb->createNamedParameter($categoryIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = $qb->executeQuery();
		$permissions = [];
		while ($row = $result->fetch()) {
			$categoryId = (int)$row['category_id'];
			if (!isset($permissions[$categoryId])) {
				$permissions[$categoryId] = [];
			}
			$permissions[$categoryId][] = [
				'role_id' => (int)$row['role_id'],
				'can_view' => (bool)$row['can_view'],
			];
		}
		$result->closeCursor();

		// Filter categories based on permissions
		return array_values(array_filter($categories, function ($category) use ($permissions, $userRoleIds) {
			$categoryId = $category->getId();

			// If no permissions exist for this category, it's public
			if (!isset($permissions[$categoryId]) || empty($permissions[$categoryId])) {
				return true;
			}

			// If user has no roles, they can't view restricted categories
			if (empty($userRoleIds)) {
				return false;
			}

			// Check if user has any role with can_view permission
			foreach ($permissions[$categoryId] as $perm) {
				if (in_array($perm['role_id'], $userRoleIds) && $perm['can_view']) {
					return true;
				}
			}

			return false;
		}));
	}

	/**
	 * @return array<Category>
	 */
	public function findByHeaderId(int $headerId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()
					->eq('header_id', $qb->createNamedParameter($headerId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('sort_order', 'ASC');
		$categories = $this->findEntities($qb);
		return $this->filterByPermissions($categories);
	}

	/**
	 * @return array<Category>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('sort_order', 'ASC');
		$categories = $this->findEntities($qb);
		return $this->filterByPermissions($categories);
	}
}
