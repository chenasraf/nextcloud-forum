<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\CatHeaderMapper;
use OCA\Forum\Db\ThreadMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CategoryController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CatHeaderMapper $catHeaderMapper,
		private CategoryMapper $categoryMapper,
		private ThreadMapper $threadMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all category headers with nested categories
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Category headers with nested categories returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/categories')]
	public function index(): DataResponse {
		try {
			// Fetch all headers and categories in just 2 queries
			$headers = $this->catHeaderMapper->findAll();
			$allCategories = $this->categoryMapper->findAll();

			// Group categories by header_id
			$categoriesByHeader = [];
			foreach ($allCategories as $category) {
				$headerId = $category->getHeaderId();
				if (!isset($categoriesByHeader[$headerId])) {
					$categoriesByHeader[$headerId] = [];
				}
				$categoriesByHeader[$headerId][] = $category->jsonSerialize();
			}

			// Build result with nested categories
			$result = [];
			foreach ($headers as $header) {
				$headerData = $header->jsonSerialize();
				$headerData['categories'] = $categoriesByHeader[$header->getId()] ?? [];
				$result[] = $headerData;
			}

			return new DataResponse($result);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching categories: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch categories'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get categories by header ID
	 *
	 * @param int $headerId Category header ID
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Categories returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/headers/{headerId}/categories')]
	public function byHeader(int $headerId): DataResponse {
		try {
			$categories = $this->categoryMapper->findByHeaderId($headerId);
			return new DataResponse(array_map(fn ($cat) => $cat->jsonSerialize(), $categories));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching categories by header: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch categories'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a single category
	 *
	 * @param int $id Category ID
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Category returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{id}')]
	public function show(int $id): DataResponse {
		try {
			$category = $this->categoryMapper->find($id);
			return new DataResponse($category->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching category: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a category by slug
	 *
	 * @param string $slug Category slug
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Category returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/categories/slug/{slug}')]
	public function bySlug(string $slug): DataResponse {
		try {
			$category = $this->categoryMapper->findBySlug($slug);
			return new DataResponse($category->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching category by slug: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new category
	 *
	 * @param int $headerId Category header ID
	 * @param string $name Category name
	 * @param string $slug Category slug
	 * @param string|null $description Category description
	 * @param int $sortOrder Sort order
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Category created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'POST', url: '/api/categories')]
	public function create(int $headerId, string $name, string $slug, ?string $description = null, int $sortOrder = 0): DataResponse {
		try {
			$category = new \OCA\Forum\Db\Category();
			$category->setHeaderId($headerId);
			$category->setName($name);
			$category->setSlug($slug);
			$category->setDescription($description);
			$category->setSortOrder($sortOrder);
			$category->setThreadCount(0);
			$category->setPostCount(0);
			$category->setCreatedAt(time());
			$category->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Category */
			$createdCategory = $this->categoryMapper->insert($category);
			return new DataResponse($createdCategory->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Exception $e) {
			$this->logger->error('Error creating category: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to create category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a category
	 *
	 * @param int $id Category ID
	 * @param string|null $name Category name
	 * @param string|null $description Category description
	 * @param string|null $slug Category slug
	 * @param int|null $sortOrder Sort order
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Category updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'PUT', url: '/api/categories/{id}')]
	public function update(int $id, ?string $name = null, ?string $description = null, ?string $slug = null, ?int $sortOrder = null): DataResponse {
		try {
			$category = $this->categoryMapper->find($id);

			if ($name !== null) {
				$category->setName($name);
			}
			if ($description !== null) {
				$category->setDescription($description);
			}
			if ($slug !== null) {
				$category->setSlug($slug);
			}
			if ($sortOrder !== null) {
				$category->setSortOrder($sortOrder);
			}
			$category->setUpdatedAt(time());

			/** @var \OCA\Forum\Db\Category */
			$updatedCategory = $this->categoryMapper->update($category);
			return new DataResponse($updatedCategory->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating category: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get thread count for a category
	 *
	 * @param int $id Category ID
	 * @return DataResponse<Http::STATUS_OK, array{count: int}, array{}>
	 *
	 * 200: Thread count returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{id}/thread-count')]
	public function getThreadCount(int $id): DataResponse {
		try {
			$this->categoryMapper->find($id);
			$count = $this->threadMapper->countByCategoryId($id);
			return new DataResponse(['count' => $count]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error fetching thread count: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch thread count'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a category
	 *
	 * @param int $id Category ID
	 * @param int|null $migrateToCategoryId Category ID to migrate threads to (null to soft-delete threads)
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, threadsAffected?: int}, array{}>
	 *
	 * 200: Category deleted
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'DELETE', url: '/api/categories/{id}')]
	public function destroy(int $id, ?int $migrateToCategoryId = null): DataResponse {
		try {
			$category = $this->categoryMapper->find($id);

			$threadsAffected = 0;

			// Handle threads migration or soft-delete
			if ($migrateToCategoryId !== null) {
				// Verify target category exists
				try {
					$this->categoryMapper->find($migrateToCategoryId);
				} catch (DoesNotExistException $e) {
					return new DataResponse(['error' => 'Target category not found'], Http::STATUS_NOT_FOUND);
				}

				// Move threads to the target category
				$threadsAffected = $this->threadMapper->moveToCategoryId($id, $migrateToCategoryId);
			} else {
				// Soft delete all threads in this category
				$threadsAffected = $this->threadMapper->softDeleteByCategoryId($id);
			}

			// Delete the category
			$this->categoryMapper->delete($category);

			return new DataResponse([
				'success' => true,
				'threadsAffected' => $threadsAffected,
			]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error deleting category: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to delete category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
