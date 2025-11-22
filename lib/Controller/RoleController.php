<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryPerm;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Service\UserRoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class RoleController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private RoleMapper $roleMapper,
		private CategoryPermMapper $categoryPermMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all roles
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Roles returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/roles')]
	public function index(): DataResponse {
		try {
			$roles = $this->roleMapper->findAll();
			return new DataResponse(array_map(fn ($role) => $role->jsonSerialize(), $roles));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching roles: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch roles'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single role
	 *
	 * @param int $id Role ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Role returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/roles/{id}')]
	public function show(int $id): DataResponse {
		try {
			$role = $this->roleMapper->find($id);
			return new DataResponse($role->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching role: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch role'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new role
	 *
	 * @param string $name Role name
	 * @param string|null $description Role description
	 * @param string|null $colorLight Light mode color
	 * @param string|null $colorDark Dark mode color
	 * @param bool $canAccessAdminTools Can access admin tools
	 * @param bool $canEditRoles Can edit roles
	 * @param bool $canEditCategories Can edit categories
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Role created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditRoles')]
	#[ApiRoute(verb: 'POST', url: '/api/roles')]
	public function create(
		string $name,
		?string $description = null,
		?string $colorLight = null,
		?string $colorDark = null,
		bool $canAccessAdminTools = false,
		bool $canEditRoles = false,
		bool $canEditCategories = false,
	): DataResponse {
		try {
			$role = new \OCA\Forum\Db\Role();
			$role->setName($name);
			$role->setDescription($description);
			$role->setColorLight($colorLight);
			$role->setColorDark($colorDark);
			$role->setCanAccessAdminTools($canAccessAdminTools);
			$role->setCanEditRoles($canEditRoles);
			$role->setCanEditCategories($canEditCategories);
			$role->setCreatedAt(time());

			/** @var \OCA\Forum\Db\Role */
			$createdRole = $this->roleMapper->insert($role);
			return new DataResponse($createdRole->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating role: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create role'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a role
	 *
	 * @param int $id Role ID
	 * @param string|null $name Role name
	 * @param string|null $description Role description
	 * @param string|null $colorLight Light mode color
	 * @param string|null $colorDark Dark mode color
	 * @param bool|null $canAccessAdminTools Can access admin tools
	 * @param bool|null $canEditRoles Can edit roles
	 * @param bool|null $canEditCategories Can edit categories
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Role updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditRoles')]
	#[ApiRoute(verb: 'PUT', url: '/api/roles/{id}')]
	public function update(
		int $id,
		?string $name = null,
		?string $description = null,
		?string $colorLight = null,
		?string $colorDark = null,
		?bool $canAccessAdminTools = null,
		?bool $canEditRoles = null,
		?bool $canEditCategories = null,
	): DataResponse {
		try {
			$role = $this->roleMapper->find($id);

			if ($name !== null) {
				$role->setName($name);
			}
			if ($description !== null) {
				$role->setDescription($description);
			}
			if ($colorLight !== null) {
				$role->setColorLight($colorLight);
			}
			if ($colorDark !== null) {
				$role->setColorDark($colorDark);
			}

			// Admin role always has all permissions - cannot be changed
			if ($id === UserRoleService::ROLE_ADMIN) {
				$role->setCanAccessAdminTools(true);
				$role->setCanEditRoles(true);
				$role->setCanEditCategories(true);
			} else {
				if ($canAccessAdminTools !== null) {
					$role->setCanAccessAdminTools($canAccessAdminTools);
				}
				if ($canEditRoles !== null) {
					$role->setCanEditRoles($canEditRoles);
				}
				if ($canEditCategories !== null) {
					$role->setCanEditCategories($canEditCategories);
				}
			}

			/** @var \OCA\Forum\Db\Role */
			$updatedRole = $this->roleMapper->update($role);
			return new DataResponse($updatedRole->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating role: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update role'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a role
	 *
	 * @param int $id Role ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 200: Role deleted
	 * 403: Cannot delete system roles
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditRoles')]
	#[ApiRoute(verb: 'DELETE', url: '/api/roles/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			// Prevent deleting system roles (Admin, Moderator, User)
			if (in_array($id, [
				UserRoleService::ROLE_ADMIN,
				UserRoleService::ROLE_MODERATOR,
				UserRoleService::ROLE_USER,
			], true)) {
				return new DataResponse(['error' => 'System roles cannot be deleted'], Http::STATUS_FORBIDDEN);
			}

			$role = $this->roleMapper->find($id);

			// Delete associated permissions
			$this->categoryPermMapper->deleteByRoleId($id);

			$this->roleMapper->delete($role);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting role: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete role'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get permissions for a role
	 *
	 * @param int $id Role ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Permissions returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/roles/{id}/permissions')]
	public function getPermissions(int $id): DataResponse {
		try {
			$permissions = $this->categoryPermMapper->findByRoleId($id);
			return new DataResponse(array_map(fn ($perm) => $perm->jsonSerialize(), $permissions));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching role permissions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch permissions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update permissions for a role
	 *
	 * @param int $id Role ID
	 * @param list<array{categoryId: int, canView: bool, canModerate: bool}> $permissions Permissions array
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Permissions updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditRoles')]
	#[ApiRoute(verb: 'POST', url: '/api/roles/{id}/permissions')]
	public function updatePermissions(int $id, array $permissions): DataResponse {
		try {
			// Verify role exists
			$this->roleMapper->find($id);

			// Delete existing permissions for this role
			$this->categoryPermMapper->deleteByRoleId($id);

			// Insert new permissions
			foreach ($permissions as $perm) {
				$categoryPerm = new CategoryPerm();
				$categoryPerm->setCategoryId($perm['categoryId']);
				$categoryPerm->setRoleId($id);
				$categoryPerm->setCanView($perm['canView'] ?? false);
				$categoryPerm->setCanPost($perm['canView'] ?? false); // Default: can post if can view
				$categoryPerm->setCanReply($perm['canView'] ?? false); // Default: can reply if can view
				$categoryPerm->setCanModerate($perm['canModerate'] ?? false);

				$this->categoryPermMapper->insert($categoryPerm);
			}

			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating role permissions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update permissions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
