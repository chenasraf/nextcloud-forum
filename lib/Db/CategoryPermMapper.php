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

/**
 * @template-extends QBMapper<CategoryPerm>
 */
class CategoryPermMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, Application::tableName('forum_category_perms'), CategoryPerm::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): CategoryPerm {
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
	 * Find permissions by team (circle) ID
	 *
	 * @return array<CategoryPerm>
	 */
	public function findByTeamId(string $teamId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_TEAM, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Find permissions by role ID
	 *
	 * @return array<CategoryPerm>
	 */
	public function findByRoleId(int $roleId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_ROLE, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter((string)$roleId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * @return array<CategoryPerm>
	 */
	public function findByCategoryId(int $categoryId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Find permissions for a category, excluding Admin role (which has implicit full access)
	 * Returns both role-type and team-type permissions.
	 *
	 * @param int $categoryId Category ID
	 * @return array<CategoryPerm>
	 */
	public function findByCategoryIdExcludingAdmin(int $categoryId): array {
		// Get all perms for this category
		$allPerms = $this->findByCategoryId($categoryId);

		// Get admin role IDs to exclude
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from(Application::tableName('forum_roles'))
			->where($qb->expr()->eq('role_type', $qb->createNamedParameter(Role::ROLE_TYPE_ADMIN, IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		$adminRoleIds = [];
		while ($row = $result->fetch()) {
			$adminRoleIds[] = (string)$row['id'];
		}
		$result->closeCursor();

		// Filter: include all team-type perms, exclude admin role-type perms
		return array_values(array_filter($allPerms, function (CategoryPerm $perm) use ($adminRoleIds) {
			if ($perm->getTargetType() === CategoryPerm::TARGET_TYPE_TEAM) {
				return true;
			}
			return !in_array($perm->getTargetId(), $adminRoleIds, true);
		}));
	}

	/**
	 * Find permission for specific category and role
	 *
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findByCategoryAndRole(int $categoryId, int $roleId): CategoryPerm {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_ROLE, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter((string)$roleId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Find permissions for specific category and multiple roles
	 *
	 * @param int $categoryId Category ID
	 * @param array<int> $roleIds Array of role IDs
	 * @return array<CategoryPerm>
	 */
	public function findByCategoryAndRoles(int $categoryId, array $roleIds): array {
		if (empty($roleIds)) {
			return [];
		}

		$roleIdStrings = array_map('strval', $roleIds);

		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_ROLE, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->in('target_id', $qb->createNamedParameter($roleIdStrings, IQueryBuilder::PARAM_STR_ARRAY))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Find permissions for specific category and multiple team (circle) IDs
	 *
	 * @param int $categoryId Category ID
	 * @param array<string> $teamIds Array of team/circle IDs
	 * @return array<CategoryPerm>
	 */
	public function findByCategoryAndTeamIds(int $categoryId, array $teamIds): array {
		if (empty($teamIds)) {
			return [];
		}

		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_TEAM, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->in('target_id', $qb->createNamedParameter($teamIds, IQueryBuilder::PARAM_STR_ARRAY))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Delete all permissions for a team (circle)
	 */
	public function deleteByTeamId(string $teamId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_TEAM, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}

	/**
	 * Delete all permissions for a role
	 */
	public function deleteByRoleId(int $roleId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('target_type', $qb->createNamedParameter(CategoryPerm::TARGET_TYPE_ROLE, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter((string)$roleId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}

	/**
	 * Delete permissions by target type and ID
	 */
	public function deleteByTargetTypeAndId(string $type, string $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('target_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('target_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}

	/**
	 * Delete all permissions for a category filtered by target type
	 */
	public function deleteByCategoryIdAndTargetType(int $categoryId, string $targetType): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('target_type', $qb->createNamedParameter($targetType, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}

	/**
	 * Delete all permissions for a category
	 */
	public function deleteByCategoryId(int $categoryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	/**
	 * @return array<CategoryPerm>
	 */
	public function findAll(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
