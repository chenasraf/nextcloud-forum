// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * System role IDs
 * These roles are created during app installation and cannot be deleted
 */
export const SystemRole = {
  /** Admin role ID */
  ADMIN: 1,
  /** Moderator role ID */
  MODERATOR: 2,
  /** User role ID */
  USER: 3,
} as const

/**
 * Check if a role ID is a system role
 */
export function isSystemRole(roleId: number): boolean {
  return Object.values(SystemRole).includes(roleId as (typeof SystemRole)[keyof typeof SystemRole])
}
