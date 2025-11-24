// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { Role } from './types/models'

/**
 * Role type constants
 * These match the role types defined in the backend (Role::ROLE_TYPE_*)
 */
export const RoleType = {
  ADMIN: 'admin',
  MODERATOR: 'moderator',
  DEFAULT: 'default',
  GUEST: 'guest',
  CUSTOM: 'custom',
} as const

export type RoleTypeValue = (typeof RoleType)[keyof typeof RoleType]

/**
 * Check if a role is a system role
 * @param role Role object to check
 * @returns True if the role is a system role (cannot be deleted)
 */
export function isSystemRole(role: Role): boolean {
  return role.isSystemRole
}

/**
 * Check if a role is the Admin role
 * @param role Role object to check
 * @returns True if the role is the Admin role
 */
export function isAdminRole(role: Role | null | undefined): boolean {
  return role?.roleType === RoleType.ADMIN
}

/**
 * Check if a role is the Moderator role
 * @param role Role object to check
 * @returns True if the role is the Moderator role
 */
export function isModeratorRole(role: Role | null | undefined): boolean {
  return role?.roleType === RoleType.MODERATOR
}

/**
 * Check if a role is the Default (user) role
 * @param role Role object to check
 * @returns True if the role is the Default role
 */
export function isDefaultRole(role: Role | null | undefined): boolean {
  return role?.roleType === RoleType.DEFAULT
}

/**
 * Check if a role is the Guest role
 * @param role Role object to check
 * @returns True if the role is the Guest role
 */
export function isGuestRole(role: Role | null | undefined): boolean {
  return role?.roleType === RoleType.GUEST
}

/**
 * Check if a role is a custom (non-system) role
 * @param role Role object to check
 * @returns True if the role is a custom role
 */
export function isCustomRole(role: Role | null | undefined): boolean {
  return role?.roleType === RoleType.CUSTOM
}
