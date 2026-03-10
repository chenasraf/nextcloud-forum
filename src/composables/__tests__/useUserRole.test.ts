import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createMockRole } from '@/test-mocks'

// Mock the ocs axios client
const mockGet = vi.fn()
vi.mock('@/axios', () => ({
  ocs: { get: (...args: unknown[]) => mockGet(...args) },
}))

// Import after mocks are set up
import { useUserRole } from '../useUserRole'

describe('useUserRole', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    const { clear } = useUserRole()
    clear()
  })

  describe('isAdmin', () => {
    it('should be true when user has admin role type', async () => {
      const adminRole = createMockRole({
        roleType: 'admin',
        canAccessAdminTools: true,
        canEditRoles: true,
        canEditCategories: true,
      })
      mockGet.mockResolvedValue({ data: [adminRole] })

      const { isAdmin, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(isAdmin.value).toBe(true)
    })

    it('should be false for custom role even with admin permissions', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: true,
        canEditCategories: true,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { isAdmin, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(isAdmin.value).toBe(false)
    })
  })

  describe('canAccessAdmin', () => {
    it('should be true for admin role type', async () => {
      const adminRole = createMockRole({
        roleType: 'admin',
        canAccessAdminTools: true,
      })
      mockGet.mockResolvedValue({ data: [adminRole] })

      const { canAccessAdmin, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canAccessAdmin.value).toBe(true)
    })

    it('should be true for custom role with canAccessAdminTools', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canAccessAdmin, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canAccessAdmin.value).toBe(true)
    })

    it('should be false when no role has canAccessAdminTools', async () => {
      const defaultRole = createMockRole({
        roleType: 'default',
        canAccessAdminTools: false,
      })
      mockGet.mockResolvedValue({ data: [defaultRole] })

      const { canAccessAdmin, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canAccessAdmin.value).toBe(false)
    })

    it('should be true when any role has canAccessAdminTools', async () => {
      const defaultRole = createMockRole({
        roleType: 'default',
        canAccessAdminTools: false,
      })
      const customRole = createMockRole({
        id: 101,
        roleType: 'custom',
        canAccessAdminTools: true,
      })
      mockGet.mockResolvedValue({ data: [defaultRole, customRole] })

      const { canAccessAdmin, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canAccessAdmin.value).toBe(true)
    })
  })

  describe('canEditRoles', () => {
    it('should be true for custom role with canEditRoles', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canEditRoles: true,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canEditRoles, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canEditRoles.value).toBe(true)
    })

    it('should be false when no role has canEditRoles', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: false,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canEditRoles, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canEditRoles.value).toBe(false)
    })
  })

  describe('canEditCategories', () => {
    it('should be true for custom role with canEditCategories', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canEditCategories: true,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canEditCategories, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canEditCategories.value).toBe(true)
    })

    it('should be false when no role has canEditCategories', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditCategories: false,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canEditCategories, fetchUserRoles } = useUserRole()
      await fetchUserRoles('user1')

      expect(canEditCategories.value).toBe(false)
    })
  })

  describe('partial admin permissions', () => {
    it('should allow admin access with only canAccessAdminTools and canEditCategories', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: false,
        canEditCategories: true,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canAccessAdmin, canEditRoles, canEditCategories, isAdmin, fetchUserRoles } =
        useUserRole()
      await fetchUserRoles('user1')

      expect(isAdmin.value).toBe(false)
      expect(canAccessAdmin.value).toBe(true)
      expect(canEditRoles.value).toBe(false)
      expect(canEditCategories.value).toBe(true)
    })

    it('should allow admin access with all permissions on custom role', async () => {
      const customRole = createMockRole({
        roleType: 'custom',
        canAccessAdminTools: true,
        canEditRoles: true,
        canEditCategories: true,
      })
      mockGet.mockResolvedValue({ data: [customRole] })

      const { canAccessAdmin, canEditRoles, canEditCategories, isAdmin, fetchUserRoles } =
        useUserRole()
      await fetchUserRoles('user1')

      expect(isAdmin.value).toBe(false)
      expect(canAccessAdmin.value).toBe(true)
      expect(canEditRoles.value).toBe(true)
      expect(canEditCategories.value).toBe(true)
    })
  })
})
