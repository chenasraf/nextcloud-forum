import { describe, it, expect, beforeEach } from 'vitest'
import { createMockRole } from '@/test-mocks'
import { useUserRole } from '../useUserRole'

describe('useUserRole', () => {
  beforeEach(() => {
    const { clear } = useUserRole()
    clear()
  })

  describe('isAdmin', () => {
    it('should be true when user has admin role type', () => {
      const adminRole = createMockRole({
        roleType: 'admin',
        canAccessAdminTools: true,
        canEditRoles: true,
        canEditCategories: true,
      })

      const { isAdmin, setRoles } = useUserRole()
      setRoles('user1', [adminRole])

      expect(isAdmin.value).toBe(true)
    })

    it('should be false for custom role even with admin permissions', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: true,
        canEditCategories: true,
      })

      const { isAdmin, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(isAdmin.value).toBe(false)
    })
  })

  describe('canAccessAdmin', () => {
    it('should be true for admin role type', () => {
      const adminRole = createMockRole({
        roleType: 'admin',
        canAccessAdminTools: true,
      })

      const { canAccessAdmin, setRoles } = useUserRole()
      setRoles('user1', [adminRole])

      expect(canAccessAdmin.value).toBe(true)
    })

    it('should be true for custom role with canAccessAdminTools', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
      })

      const { canAccessAdmin, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(canAccessAdmin.value).toBe(true)
    })

    it('should be false when no role has any management permission', () => {
      const defaultRole = createMockRole({
        roleType: 'default',
        canAccessAdminTools: false,
      })

      const { canAccessAdmin, setRoles } = useUserRole()
      setRoles('user1', [defaultRole])

      expect(canAccessAdmin.value).toBe(false)
    })

    it('should be true when any role has canAccessAdminTools', () => {
      const defaultRole = createMockRole({
        roleType: 'default',
        canAccessAdminTools: false,
      })
      const customRole = createMockRole({
        id: 101,
        roleType: 'custom',
        canAccessAdminTools: true,
      })

      const { canAccessAdmin, setRoles } = useUserRole()
      setRoles('user1', [defaultRole, customRole])

      expect(canAccessAdmin.value).toBe(true)
    })

    it('should be true when role has only canManageUsers', () => {
      const role = createMockRole({ canManageUsers: true })

      const { canAccessAdmin, setRoles } = useUserRole()
      setRoles('user1', [role])

      expect(canAccessAdmin.value).toBe(true)
    })

    it('should be true when role has only canEditBbcodes', () => {
      const role = createMockRole({ canEditBbcodes: true })

      const { canAccessAdmin, setRoles } = useUserRole()
      setRoles('user1', [role])

      expect(canAccessAdmin.value).toBe(true)
    })
  })

  describe('canEditRoles', () => {
    it('should be true for custom role with canEditRoles', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canEditRoles: true,
      })

      const { canEditRoles, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(canEditRoles.value).toBe(true)
    })

    it('should be false when no role has canEditRoles', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: false,
      })

      const { canEditRoles, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(canEditRoles.value).toBe(false)
    })
  })

  describe('canEditCategories', () => {
    it('should be true for custom role with canEditCategories', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canEditCategories: true,
      })

      const { canEditCategories, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(canEditCategories.value).toBe(true)
    })

    it('should be false when no role has canEditCategories', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditCategories: false,
      })

      const { canEditCategories, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(canEditCategories.value).toBe(false)
    })
  })

  describe('canManageUsers', () => {
    it('should be true when role has canManageUsers', () => {
      const role = createMockRole({ canManageUsers: true })

      const { canManageUsers, setRoles } = useUserRole()
      setRoles('user1', [role])

      expect(canManageUsers.value).toBe(true)
    })

    it('should be false when no role has canManageUsers', () => {
      const role = createMockRole({ canManageUsers: false })

      const { canManageUsers, setRoles } = useUserRole()
      setRoles('user1', [role])

      expect(canManageUsers.value).toBe(false)
    })
  })

  describe('canEditBbcodes', () => {
    it('should be true when role has canEditBbcodes', () => {
      const role = createMockRole({ canEditBbcodes: true })

      const { canEditBbcodes, setRoles } = useUserRole()
      setRoles('user1', [role])

      expect(canEditBbcodes.value).toBe(true)
    })

    it('should be false when no role has canEditBbcodes', () => {
      const role = createMockRole({ canEditBbcodes: false })

      const { canEditBbcodes, setRoles } = useUserRole()
      setRoles('user1', [role])

      expect(canEditBbcodes.value).toBe(false)
    })
  })

  describe('partial admin permissions', () => {
    it('should allow admin access with only canAccessAdminTools and canEditCategories', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: false,
        canEditCategories: true,
      })

      const { canAccessAdmin, canEditRoles, canEditCategories, isAdmin, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(isAdmin.value).toBe(false)
      expect(canAccessAdmin.value).toBe(true)
      expect(canEditRoles.value).toBe(false)
      expect(canEditCategories.value).toBe(true)
    })

    it('should allow admin access with all permissions on custom role', () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canManageUsers: true,
        canEditRoles: true,
        canEditCategories: true,
        canEditBbcodes: true,
      })

      const { canAccessAdmin, canEditRoles, canEditCategories, isAdmin, setRoles } = useUserRole()
      setRoles('user1', [customRole])

      expect(isAdmin.value).toBe(false)
      expect(canAccessAdmin.value).toBe(true)
      expect(canEditRoles.value).toBe(true)
      expect(canEditCategories.value).toBe(true)
    })
  })
})
