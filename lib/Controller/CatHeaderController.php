<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Db\CatHeaderMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CatHeaderController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CatHeaderMapper $catHeaderMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all category headers
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Category headers returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/headers')]
	public function index(): DataResponse {
		try {
			$headers = $this->catHeaderMapper->findAll();
			return new DataResponse(array_map(fn ($h) => $h->jsonSerialize(), $headers));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching category headers: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch category headers'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single category header
	 *
	 * @param int $id Category header ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Category header returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/headers/{id}')]
	public function show(int $id): DataResponse {
		try {
			$header = $this->catHeaderMapper->find($id);
			return new DataResponse($header->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category header not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching category header: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch category header'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new category header
	 *
	 * @param string $name Category header name
	 * @param string|null $description Category header description
	 * @param int $sortOrder Sort order
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Category header created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/headers')]
	public function create(string $name, ?string $description = null, int $sortOrder = 0): DataResponse {
		try {
			$header = new \OCA\Forum\Db\CatHeader();
			$header->setName($name);
			$header->setDescription($description);
			$header->setSortOrder($sortOrder);
			$header->setCreatedAt(time());

			/** @var \OCA\Forum\Db\CatHeader */
			$createdHeader = $this->catHeaderMapper->insert($header);
			return new DataResponse($createdHeader->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating category header: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create category header'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a category header
	 *
	 * @param int $id Category header ID
	 * @param string|null $name Category header name
	 * @param string|null $description Category header description
	 * @param int|null $sortOrder Sort order
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Category header updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/headers/{id}')]
	public function update(int $id, ?string $name = null, ?string $description = null, ?int $sortOrder = null): DataResponse {
		try {
			$header = $this->catHeaderMapper->find($id);

			if ($name !== null) {
				$header->setName($name);
			}
			if ($description !== null) {
				$header->setDescription($description);
			}
			if ($sortOrder !== null) {
				$header->setSortOrder($sortOrder);
			}

			/** @var \OCA\Forum\Db\CatHeader */
			$updatedHeader = $this->catHeaderMapper->update($header);
			return new DataResponse($updatedHeader->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category header not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating category header: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update category header'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a category header
	 *
	 * @param int $id Category header ID
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Category header deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/headers/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$header = $this->catHeaderMapper->find($id);
			$this->catHeaderMapper->delete($header);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category header not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting category header: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete category header'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
