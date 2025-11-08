<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Forum\Attribute;

use Attribute;

/**
 * Require permission to access endpoint
 *
 * This attribute can be used to enforce permission checks on controller methods.
 * It supports both global permissions (role-based) and resource-specific permissions
 * (e.g., category permissions).
 *
 * Examples:
 *   #[RequirePermission('canEditRoles')]  // Global permission
 *   #[RequirePermission('canView', resourceType: 'category', resourceIdParam: 'id')]  // Category permission from route param
 *   #[RequirePermission('canPost', resourceType: 'category', resourceIdParam: 'categoryId')]  // Category permission
 *   #[RequirePermission('canReply', resourceType: 'category', resourceIdFromThreadId: 'threadId')]  // Derive category from thread
 *   #[RequirePermission('canModerate', resourceType: 'category', resourceIdFromPostId: 'id')]  // Derive category from post
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RequirePermission {
	public function __construct(
		// Permission name (matches DB column name in camelCase, e.g., 'canEditRoles', 'canView', 'canPost')
		private string $permission,
		// For resource-specific permissions: 'category', 'thread', 'post', etc.
		private ?string $resourceType = null,
		// Get resource ID from route/query parameter
		private ?string $resourceIdParam = null,
		// Get resource ID from request body
		private ?string $resourceIdBody = null,
		// Derive category ID from thread ID parameter
		private ?string $resourceIdFromThreadId = null,
		// Derive category ID from post ID parameter
		private ?string $resourceIdFromPostId = null,
	) {
	}

	public function getPermission(): string {
		return $this->permission;
	}

	public function getResourceType(): ?string {
		return $this->resourceType;
	}

	public function getResourceIdParam(): ?string {
		return $this->resourceIdParam;
	}

	public function getResourceIdBody(): ?string {
		return $this->resourceIdBody;
	}

	public function getResourceIdFromThreadId(): ?string {
		return $this->resourceIdFromThreadId;
	}

	public function getResourceIdFromPostId(): ?string {
		return $this->resourceIdFromPostId;
	}
}
