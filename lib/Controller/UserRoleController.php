<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\UserRoleMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class UserRoleController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private UserRoleMapper $userRoleMapper,
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
	#[ApiRoute(verb: 'GET', url: '/api/users/{userId}/roles')]
	public function byUser(string $userId): DataResponse {
		try {
			$userRoles = $this->userRoleMapper->findByUserId($userId);
			return new DataResponse(array_map(fn ($ur) => $ur->jsonSerialize(), $userRoles));
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
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Role assigned to user
	 */
	#[ApiRoute(verb: 'POST', url: '/api/user-roles')]
	public function create(string $userId, int $roleId): DataResponse {
		try {
			$userRole = new \OCA\Forum\Db\UserRole();
			$userRole->setUserId($userId);
			$userRole->setRoleId($roleId);
			$userRole->setCreatedAt(time());

			$createdUserRole = $this->userRoleMapper->insert($userRole);
			return new DataResponse($createdUserRole->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error assigning role to user: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to assign role to user'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Remove a role from a user
	 *
	 * @param int $id User role ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Role removed from user
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/user-roles/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$userRole = $this->userRoleMapper->find($id);
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
