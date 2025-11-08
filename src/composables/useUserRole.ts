import { ref, computed } from 'vue'
import { ocs } from '@/axios'
import type { UserRole } from '@/types'

const userRoles = ref<UserRole[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

export function useUserRole() {
  const fetchUserRoles = async (userId: string, force = false): Promise<UserRole[]> => {
    if (loaded.value && !force) {
      return userRoles.value
    }

    try {
      loading.value = true
      error.value = null
      const response = await ocs.get<UserRole[]>(`/users/${userId}/roles`)
      userRoles.value = response.data || []
      loaded.value = true
      return userRoles.value
    } catch (e) {
      error.value = (e as Error).message || 'Failed to fetch user roles'
      console.error('Failed to fetch user roles:', e)
      return []
    } finally {
      loading.value = false
    }
  }

  const isAdmin = computed<boolean>(() => {
    // Admin role has ID 1 (from migration)
    return userRoles.value.some((role) => role.roleId === 1)
  })

  const refresh = () => {
    loaded.value = false
    const userId = userRoles.value[0]?.userId
    if (userId) {
      return fetchUserRoles(userId, true)
    }
  }

  const clear = () => {
    userRoles.value = []
    loaded.value = false
    error.value = null
  }

  return {
    userRoles,
    loading,
    error,
    loaded,
    isAdmin,
    fetchUserRoles,
    refresh,
    clear,
  }
}
