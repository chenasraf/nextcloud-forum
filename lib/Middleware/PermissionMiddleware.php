<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Middleware;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Middleware to enforce permission checks based on RequirePermission attributes
 */
class PermissionMiddleware extends Middleware {
	public function __construct(
		private IRequest $request,
		private IUserSession $userSession,
		private PermissionService $permissionService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check permissions before controller method is called
	 *
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws OCSForbiddenException If user lacks required permissions
	 */
	public function beforeController($controller, $methodName): void {
		$user = $this->userSession->getUser();
		if (!$user) {
			$this->logger->debug('Permission check failed: User not authenticated');
			throw new OCSForbiddenException('User not authenticated');
		}

		$userId = $user->getUID();
		$reflectionMethod = new ReflectionMethod($controller, $methodName);

		// Get all RequirePermission attributes on the method
		$permissionAttrs = $reflectionMethod->getAttributes(RequirePermission::class);

		if (empty($permissionAttrs)) {
			// No permission requirements, allow access
			return;
		}

		// Check each permission requirement
		foreach ($permissionAttrs as $attr) {
			/** @var RequirePermission $permission */
			$permission = $attr->newInstance();
			$this->checkPermission($userId, $permission);
		}
	}

	/**
	 * Check a single permission requirement
	 *
	 * @param string $userId Nextcloud user ID
	 * @param RequirePermission $permission Permission requirement to check
	 * @throws OCSForbiddenException If permission check fails
	 */
	private function checkPermission(string $userId, RequirePermission $permission): void {
		$permissionName = $permission->getPermission();
		$resourceType = $permission->getResourceType();

		// Global permission check
		if ($resourceType === null) {
			if (!$this->permissionService->hasGlobalPermission($userId, $permissionName)) {
				$this->logger->info("User $userId denied access: lacks global permission '$permissionName'");
				throw new OCSForbiddenException("Insufficient permissions: $permissionName");
			}
			return;
		}

		// Resource-specific permission check
		try {
			$resourceId = $this->resolveResourceId($permission);

			if ($resourceType === 'category') {
				if (!$this->permissionService->hasCategoryPermission($userId, $resourceId, $permissionName)) {
					$this->logger->info("User $userId denied access: lacks category permission '$permissionName' on category $resourceId");
					throw new OCSForbiddenException("Insufficient category permissions: $permissionName");
				}
			} else {
				$this->logger->warning("Unknown resource type: $resourceType");
				throw new OCSForbiddenException('Invalid permission configuration');
			}
		} catch (\InvalidArgumentException $e) {
			$this->logger->error('Failed to resolve resource ID: ' . $e->getMessage());
			throw new OCSForbiddenException('Invalid request: cannot determine resource');
		} catch (\Exception $e) {
			$this->logger->error('Permission check error: ' . $e->getMessage());
			throw new OCSForbiddenException('Permission check failed');
		}
	}

	/**
	 * Resolve resource ID from request based on permission configuration
	 *
	 * @param RequirePermission $permission Permission configuration
	 * @return int Resolved resource ID
	 * @throws \InvalidArgumentException If resource ID cannot be resolved
	 */
	private function resolveResourceId(RequirePermission $permission): int {
		// From route parameter (e.g., /api/categories/{id})
		if ($param = $permission->getResourceIdParam()) {
			$value = $this->request->getParam($param);
			if ($value !== null) {
				return (int)$value;
			}
			throw new \InvalidArgumentException("Route parameter '$param' not found");
		}

		// From request body (e.g., POST {categoryId: 5})
		if ($body = $permission->getResourceIdBody()) {
			$data = $this->request->getParams();
			if (isset($data[$body])) {
				return (int)$data[$body];
			}
			throw new \InvalidArgumentException("Request body parameter '$body' not found");
		}

		// Derive category ID from thread ID
		if ($threadParam = $permission->getResourceIdFromThreadId()) {
			$threadId = $this->request->getParam($threadParam);
			if ($threadId !== null) {
				return $this->permissionService->getCategoryIdFromThread((int)$threadId);
			}
			throw new \InvalidArgumentException("Thread ID parameter '$threadParam' not found");
		}

		// Derive category ID from post ID
		if ($postParam = $permission->getResourceIdFromPostId()) {
			$postId = $this->request->getParam($postParam);
			if ($postId !== null) {
				return $this->permissionService->getCategoryIdFromPost((int)$postId);
			}
			throw new \InvalidArgumentException("Post ID parameter '$postParam' not found");
		}

		throw new \InvalidArgumentException('Cannot resolve resource ID: no valid parameter configuration');
	}

	/**
	 * Handle exceptions thrown in beforeController
	 *
	 * @param Controller $controller
	 * @param string $methodName
	 * @param \Exception $exception
	 * @return Response
	 * @throws \Exception
	 */
	public function afterException($controller, $methodName, \Exception $exception): Response {
		// Re-throw OCS exceptions (they'll be handled by the framework)
		if ($exception instanceof OCSException) {
			throw $exception;
		}

		// Log and re-throw other exceptions
		$this->logger->error('Unexpected exception in PermissionMiddleware: ' . $exception->getMessage(), [
			'exception' => $exception,
		]);
		throw $exception;
	}
}
