import { ref, computed } from 'vue'
import { isAdminRole, isModeratorRole } from '@/constants'
import type { Role } from '@/types'

const userRoles = ref<Role[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)
const currentUserId = ref<string | null>(null)

export function useUserRole() {
  /**
   * Set roles directly (called by useCurrentUser after fetching /users/me)
   */
  const setRoles = (userId: string, roles: Role[]): void => {
    userRoles.value = roles
    currentUserId.value = userId
    loaded.value = true
  }

  const isAdmin = computed<boolean>(() => {
    return userRoles.value.some(isAdminRole)
  })

  const isModerator = computed<boolean>(() => {
    return userRoles.value.some(isModeratorRole)
  })

  const canAccessAdminTools = computed<boolean>(() => {
    return userRoles.value.some((role) => role.canAccessAdminTools)
  })

  const canManageUsers = computed<boolean>(() => {
    return userRoles.value.some((role) => role.canManageUsers)
  })

  const canEditRoles = computed<boolean>(() => {
    return userRoles.value.some((role) => role.canEditRoles)
  })

  const canEditCategories = computed<boolean>(() => {
    return userRoles.value.some((role) => role.canEditCategories)
  })

  const canEditBbcodes = computed<boolean>(() => {
    return userRoles.value.some((role) => role.canEditBbcodes)
  })

  const canAccessAdmin = computed<boolean>(() => {
    return (
      canAccessAdminTools.value ||
      canManageUsers.value ||
      canEditRoles.value ||
      canEditCategories.value ||
      canEditBbcodes.value
    )
  })

  const clear = () => {
    userRoles.value = []
    currentUserId.value = null
    loaded.value = false
    error.value = null
  }

  return {
    userRoles,
    loading,
    error,
    loaded,
    isAdmin,
    isModerator,
    canAccessAdmin,
    canAccessAdminTools,
    canManageUsers,
    canEditRoles,
    canEditCategories,
    canEditBbcodes,
    setRoles,
    clear,
  }
}
