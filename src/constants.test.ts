import { describe, it, expect } from 'vitest'
import {
  RoleType,
  isSystemRole,
  isAdminRole,
  isModeratorRole,
  isDefaultRole,
  isGuestRole,
  isCustomRole,
} from './constants'
import type { Role } from './types/models'

// Helper to create a mock role
function createMockRole(overrides: Partial<Role> = {}): Role {
  return {
    id: 1,
    name: 'Test Role',
    description: null,
    colorLight: null,
    colorDark: null,
    canAccessAdminTools: false,
    canEditRoles: false,
    canEditCategories: false,
    isSystemRole: false,
    roleType: 'custom',
    createdAt: Date.now(),
    ...overrides,
  }
}

describe('RoleType constants', () => {
  it('should have correct values', () => {
    expect(RoleType.ADMIN).toBe('admin')
    expect(RoleType.MODERATOR).toBe('moderator')
    expect(RoleType.DEFAULT).toBe('default')
    expect(RoleType.GUEST).toBe('guest')
    expect(RoleType.CUSTOM).toBe('custom')
  })
})

describe('isSystemRole', () => {
  it('should return true for system roles', () => {
    const role = createMockRole({ isSystemRole: true })
    expect(isSystemRole(role)).toBe(true)
  })

  it('should return false for non-system roles', () => {
    const role = createMockRole({ isSystemRole: false })
    expect(isSystemRole(role)).toBe(false)
  })
})

describe('isAdminRole', () => {
  it('should return true for admin roles', () => {
    const role = createMockRole({ roleType: 'admin' })
    expect(isAdminRole(role)).toBe(true)
  })

  it('should return false for non-admin roles', () => {
    const role = createMockRole({ roleType: 'moderator' })
    expect(isAdminRole(role)).toBe(false)
  })

  it('should return false for null/undefined', () => {
    expect(isAdminRole(null)).toBe(false)
    expect(isAdminRole(undefined)).toBe(false)
  })
})

describe('isModeratorRole', () => {
  it('should return true for moderator roles', () => {
    const role = createMockRole({ roleType: 'moderator' })
    expect(isModeratorRole(role)).toBe(true)
  })

  it('should return false for non-moderator roles', () => {
    const role = createMockRole({ roleType: 'admin' })
    expect(isModeratorRole(role)).toBe(false)
  })

  it('should return false for null/undefined', () => {
    expect(isModeratorRole(null)).toBe(false)
    expect(isModeratorRole(undefined)).toBe(false)
  })
})

describe('isDefaultRole', () => {
  it('should return true for default roles', () => {
    const role = createMockRole({ roleType: 'default' })
    expect(isDefaultRole(role)).toBe(true)
  })

  it('should return false for non-default roles', () => {
    const role = createMockRole({ roleType: 'admin' })
    expect(isDefaultRole(role)).toBe(false)
  })

  it('should return false for null/undefined', () => {
    expect(isDefaultRole(null)).toBe(false)
    expect(isDefaultRole(undefined)).toBe(false)
  })
})

describe('isGuestRole', () => {
  it('should return true for guest roles', () => {
    const role = createMockRole({ roleType: 'guest' })
    expect(isGuestRole(role)).toBe(true)
  })

  it('should return false for non-guest roles', () => {
    const role = createMockRole({ roleType: 'admin' })
    expect(isGuestRole(role)).toBe(false)
  })

  it('should return false for null/undefined', () => {
    expect(isGuestRole(null)).toBe(false)
    expect(isGuestRole(undefined)).toBe(false)
  })
})

describe('isCustomRole', () => {
  it('should return true for custom roles', () => {
    const role = createMockRole({ roleType: 'custom' })
    expect(isCustomRole(role)).toBe(true)
  })

  it('should return false for non-custom roles', () => {
    const role = createMockRole({ roleType: 'admin' })
    expect(isCustomRole(role)).toBe(false)
  })

  it('should return false for null/undefined', () => {
    expect(isCustomRole(null)).toBe(false)
    expect(isCustomRole(undefined)).toBe(false)
  })
})
