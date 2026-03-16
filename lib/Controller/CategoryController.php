<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Controller;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Db\CategoryMapper;
use OCA\Forum\Db\CategoryPerm;
use OCA\Forum\Db\CategoryPermMapper;
use OCA\Forum\Db\CatHeaderMapper;
use OCA\Forum\Db\ReadMarkerMapper;
use OCA\Forum\Db\Role;
use OCA\Forum\Db\RoleMapper;
use OCA\Forum\Db\ThreadMapper;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class CategoryController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CatHeaderMapper $catHeaderMapper,
		private CategoryMapper $categoryMapper,
		private CategoryPermMapper $categoryPermMapper,
		private ThreadMapper $threadMapper,
		private ReadMarkerMapper $readMarkerMapper,
		private RoleMapper $roleMapper,
		private PermissionService $permissionService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all category headers with nested categories
	 *
	 * @param int<1, 100> $limit Maximum number of category headers to return
	 * @param int<0, max> $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Category headers with nested categories returned
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/categories')]
	public function index(int $limit = 100, int $offset = 0): DataResponse {
		try {
			// Fetch all headers, categories, and last activity timestamps
			$headers = $this->catHeaderMapper->findAll();
			$allCategories = $this->categoryMapper->findAll();
			$lastActivityMap = $this->threadMapper->getLastActivityByCategories();

			// Filter categories by canView permission
			$user = $this->userSession->getUser();
			$userId = $user ? $user->getUID() : null;
			$accessibleCategoryIds = $this->permissionService->getAccessibleCategories($userId);

			// Fetch category read markers for authenticated users
			$readMarkerMap = [];
			if ($user) {
				$markers = $this->readMarkerMapper->findCategoryMarkersByUserId($user->getUID());
				foreach ($markers as $marker) {
					$readMarkerMap[$marker->getEntityId()] = $marker->getReadAt();
				}
			}

			// Group accessible categories by header_id
			$categoriesByHeader = [];
			foreach ($allCategories as $category) {
				if (!in_array($category->getId(), $accessibleCategoryIds, true)) {
					continue;
				}
				$headerId = $category->getHeaderId();
				if (!isset($categoriesByHeader[$headerId])) {
					$categoriesByHeader[$headerId] = [];
				}
				$categoryData = $category->jsonSerialize();
				$categoryData['lastActivityAt'] = $lastActivityMap[$category->getId()] ?? null;
				$categoryData['readAt'] = $readMarkerMap[$category->getId()] ?? null;
				$categoriesByHeader[$headerId][] = $categoryData;
			}

			// Build result with nested categories (only include headers that have accessible categories)
			$result = [];
			foreach ($headers as $header) {
				$categories = $categoriesByHeader[$header->getId()] ?? [];
				if (empty($categories)) {
					continue;
				}
				$headerData = $header->jsonSerialize();
				$headerData['categories'] = $categories;
				$result[] = $headerData;
			}

			return new DataResponse(array_slice($result, $offset, $limit));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching categories: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch categories'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get categories by header ID
	 *
	 * @param int $headerId Category header ID
	 * @param int<1, 100> $limit Maximum number of categories to return
	 * @param int<0, max> $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Categories returned
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/headers/{headerId}/categories')]
	public function byHeader(int $headerId, int $limit = 100, int $offset = 0): DataResponse {
		try {
			$user = $this->userSession->getUser();
			$userId = $user ? $user->getUID() : null;
			$accessibleCategoryIds = $this->permissionService->getAccessibleCategories($userId);

			$categories = $this->categoryMapper->findByHeaderId($headerId);
			$filtered = array_filter($categories, fn ($cat) => in_array($cat->getId(), $accessibleCategoryIds, true));
			return new DataResponse(array_slice(array_values(array_map(fn ($cat) => $cat->jsonSerialize(), $filtered)), $offset, $limit));
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
	#[PublicPage]
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
	#[PublicPage]
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
	 * @param string|null $color Category color (hex, e.g. #dc2626)
	 * @param string|null $textColor Text color mode ('light' or 'dark')
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Category created
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'POST', url: '/api/categories')]
	public function create(int $headerId, string $name, string $slug, ?string $description = null, int $sortOrder = 0, ?string $color = null, ?string $textColor = null): DataResponse {
		try {
			$category = new \OCA\Forum\Db\Category();
			$category->setHeaderId($headerId);
			$category->setName($name);
			$category->setSlug($slug);
			$category->setDescription($description);
			$category->setSortOrder($sortOrder);
			$category->setColor($color);
			$category->setTextColor($textColor);
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
	 * @param int|null $headerId Category header ID
	 * @param string|null $name Category name
	 * @param string|null $description Category description
	 * @param string|null $slug Category slug
	 * @param int|null $sortOrder Sort order
	 * @param string|null $color Category color (hex, e.g. #dc2626)
	 * @param string|null $textColor Text color mode ('light' or 'dark')
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Category updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'PUT', url: '/api/categories/{id}')]
	public function update(int $id, ?int $headerId = null, ?string $name = null, ?string $description = null, ?string $slug = null, ?int $sortOrder = null, ?string $color = '__unset__', ?string $textColor = '__unset__'): DataResponse {
		try {
			$category = $this->categoryMapper->find($id);

			if ($headerId !== null) {
				$category->setHeaderId($headerId);
			}
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
			if ($color !== '__unset__') {
				$category->setColor($color);
			}
			if ($textColor !== '__unset__') {
				$category->setTextColor($textColor);
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
	#[PublicPage]
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

	/**
	 * Check if current user has a specific permission on a category
	 *
	 * @param int $id Category ID
	 * @param string $permission Permission name (canView, canPost, canReply, canModerate)
	 * @return DataResponse<Http::STATUS_OK, array{hasPermission: bool}, array{}>
	 *
	 * 200: Permission check result
	 */
	#[NoAdminRequired]
	#[PublicPage]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{id}/permissions/{permission}')]
	public function checkPermission(int $id, string $permission): DataResponse {
		try {
			$user = $this->userSession->getUser();
			$userId = $user?->getUID();

			$hasPermission = $this->permissionService->hasCategoryPermission($userId, $id, $permission);

			return new DataResponse(['hasPermission' => $hasPermission]);
		} catch (\Exception $e) {
			$this->logger->error("Error checking permission {$permission} for category {$id}: " . $e->getMessage());
			return new DataResponse(['hasPermission' => false]);
		}
	}

	/**
	 * Get permissions for a category
	 *
	 * @param int $id Category ID
	 * @param int<1, 100> $limit Maximum number of permissions to return
	 * @param int<0, max> $offset Offset for pagination
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Permissions returned
	 */
	#[NoAdminRequired]
	#[RequirePermission('canAccessAdminTools')]
	#[ApiRoute(verb: 'GET', url: '/api/categories/{id}/permissions')]
	public function getPermissions(int $id, int $limit = 100, int $offset = 0): DataResponse {
		try {
			// Exclude Admin role - it has hardcoded full access to all categories
			$permissions = array_slice($this->categoryPermMapper->findByCategoryIdExcludingAdmin($id), $offset, $limit);
			return new DataResponse(array_map(fn ($perm) => $perm->jsonSerialize(), $permissions));
		} catch (\Exception $e) {
			$this->logger->error('Error fetching category permissions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to fetch permissions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update permissions for a category
	 *
	 * @param int $id Category ID
	 * @param list<array{roleId: int, canView: bool, canPost: bool, canReply: bool, canModerate: bool}> $permissions Role permissions array
	 * @param list<array{teamId: string, canView: bool, canPost: bool, canReply: bool, canModerate: bool}> $teamPermissions Team permissions array
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Permissions updated
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'POST', url: '/api/categories/{id}/permissions')]
	public function updatePermissions(int $id, array $permissions, array $teamPermissions = []): DataResponse {
		try {
			// Verify category exists
			$this->categoryMapper->find($id);

			// Delete existing role permissions for this category
			$this->categoryPermMapper->deleteByCategoryIdAndTargetType($id, CategoryPerm::TARGET_TYPE_ROLE);

			// Filter out Admin role - it has hardcoded full access
			$filteredPermissions = array_filter($permissions, function ($perm) {
				$roleId = $perm['roleId'] ?? null;
				if ($roleId === null) {
					return false;
				}
				try {
					$role = $this->roleMapper->find($roleId);
					return $role->getRoleType() !== Role::ROLE_TYPE_ADMIN;
				} catch (DoesNotExistException $e) {
					return false;
				}
			});

			// Insert role permissions
			foreach ($filteredPermissions as $perm) {
				$categoryPerm = new CategoryPerm();
				$categoryPerm->setCategoryId($id);
				$categoryPerm->setTargetType(CategoryPerm::TARGET_TYPE_ROLE);
				$categoryPerm->setTargetId((string)$perm['roleId']);
				$categoryPerm->setCanView($perm['canView'] ?? false);
				$categoryPerm->setCanPost($perm['canPost'] ?? $perm['canView'] ?? false);
				$categoryPerm->setCanReply($perm['canReply'] ?? $perm['canPost'] ?? $perm['canView'] ?? false);

				// Guest and Default roles never have moderate permission
				try {
					$role = $this->roleMapper->find($perm['roleId']);
					$canModerate = $role->isModeratorRestricted() ? false : ($perm['canModerate'] ?? false);
					$categoryPerm->setCanModerate($canModerate);
				} catch (DoesNotExistException $e) {
					$categoryPerm->setCanModerate(false);
				}

				$this->categoryPermMapper->insert($categoryPerm);
			}

			// Delete existing team permissions for this category and re-insert
			$this->categoryPermMapper->deleteByCategoryIdAndTargetType($id, CategoryPerm::TARGET_TYPE_TEAM);

			foreach ($teamPermissions as $perm) {
				$teamId = $perm['teamId'] ?? null;
				if ($teamId === null) {
					continue;
				}

				$categoryPerm = new CategoryPerm();
				$categoryPerm->setCategoryId($id);
				$categoryPerm->setTargetType(CategoryPerm::TARGET_TYPE_TEAM);
				$categoryPerm->setTargetId((string)$teamId);
				$categoryPerm->setCanView($perm['canView'] ?? false);
				$categoryPerm->setCanPost($perm['canPost'] ?? false);
				$categoryPerm->setCanReply($perm['canReply'] ?? false);
				$categoryPerm->setCanModerate($perm['canModerate'] ?? false);
				$this->categoryPermMapper->insert($categoryPerm);
			}

			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error updating category permissions: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to update permissions'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Reorder categories
	 *
	 * @param list<array{id: int, sortOrder: int}> $categories Array of categories with new sort orders
	 * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>
	 *
	 * 200: Categories reordered successfully
	 */
	#[NoAdminRequired]
	#[RequirePermission('canEditCategories')]
	#[ApiRoute(verb: 'POST', url: '/api/categories/reorder')]
	public function reorder(array $categories): DataResponse {
		try {
			foreach ($categories as $categoryData) {
				$category = $this->categoryMapper->find($categoryData['id']);
				$category->setSortOrder($categoryData['sortOrder']);
				$this->categoryMapper->update($category);
			}

			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Category not found'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('Error reordering categories: ' . $e->getMessage());
			return new DataResponse(['error' => 'Failed to reorder categories'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
