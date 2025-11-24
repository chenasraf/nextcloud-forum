<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\UserRoleMapper;
use OCA\Forum\Service\UserRoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class UserRoleController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private UserRoleMapper $userRoleMapper,
		private RoleMapper $roleMapper,
		private UserRoleService $userRoleService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get roles for a user
	 *
	 * @param string $userId Nextcloud user ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: User roles returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/users/{userId}/roles')]
	public function byUser(string $userId): DataResponse {
		try {
			// Return full Role objects with roleType, not just UserRole entities
			$roles = $this->roleMapper->findByUserId($userId);
			return new DataResponse(array_map(fn ($role) => $role->jsonSerialize(), $roles));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching user roles: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch user roles'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get users with a specific role
	 *
	 * @param int $roleId Role ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: User roles returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/roles/{roleId}/users')]
	public function byRole(int $roleId): DataResponse {
		try {
			$userRoles = $this->userRoleMapper->findByRoleId($roleId);
			return new DataResponse(array_map(fn ($ur) => $ur->jsonSerialize(), $userRoles));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching users by role: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch users by role'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Assign a role to a user
	 *
	 * @param string $userId Nextcloud user ID
	 * @param int $roleId Role ID
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>|DataResponse<Http::STATUS_CONFLICT, array{error: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 201: Role assigned to user
	 * 403: Cannot assign guest role to users
	 * 409: User already has this role
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditRoles')]
	#[ApiRoute(verb: 'POST', url: '/api/user-roles')]
	public function create(string $userId, int $roleId): DataResponse {
		try {
			// Check if the role is the guest role
			$role = $this->roleMapper->find($roleId);
			if ($role->getRoleType() === \OCA\Forum\Db\Role::ROLE_TYPE_GUEST) {
				return new DataResponse(['error' => 'Guest role cannot be assigned to users'], Http::STATUS_FORBIDDEN);
			}

			// Check if user already has the role
			if ($this->userRoleService->hasRole($userId, $roleId)) {
				return new DataResponse(['error' => 'User already has this role'], Http::STATUS_CONFLICT);
			}

			// Assign the role using the service
			$createdUserRole = $this->userRoleService->assignRole($userId, $roleId, skipIfExists: false);

			if ($createdUserRole === null) {
				// This shouldn't happen since we checked hasRole above, but handle it just in case
				return new DataResponse(['error' => 'Failed to assign role'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			return new DataResponse($createdUserRole->jsonSerialize(), Http::STATUS_CREATED);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error assigning role to user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to assign role to user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Remove a role from a user
	 *
	 * @param int $id User role ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{error: string}, array{}>
	 *
	 * 200: Role removed from user
	 * 403: Cannot remove guest role from users
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditRoles')]
	#[ApiRoute(verb: 'DELETE', url: '/api/user-roles/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$userRole = $this->userRoleMapper->find($id);

			// Check if the role being removed is the guest role
			$role = $this->roleMapper->find($userRole->getRoleId());
			if ($role->getRoleType() === \OCA\Forum\Db\Role::ROLE_TYPE_GUEST) {
				return new DataResponse(['error' => 'Guest role cannot be removed from users'], Http::STATUS_FORBIDDEN);
			}

			$this->userRoleMapper->delete($userRole);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'User role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error removing role from user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to remove role from user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
