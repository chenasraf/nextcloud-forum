<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\RoleMapper;
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
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Role created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/roles')]
	public function create(string $name, ?string $description = null): DataResponse {
		try {
			$role = new \OCA\Forum\Db\Role();
			$role->setName($name);
			$role->setDescription($description);
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
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Role updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/roles/{id}')]
	public function update(int $id, ?string $name = null, ?string $description = null): DataResponse {
		try {
			$role = $this->roleMapper->find($id);

			if ($name !== null) {
				$role->setName($name);
			}
			if ($description !== null) {
				$role->setDescription($description);
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
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Role deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/roles/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$role = $this->roleMapper->find($id);
			$this->roleMapper->delete($role);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Role not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting role: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete role'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
