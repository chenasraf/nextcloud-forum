import { ref, computed } from 'vue'
import { ocs } from '@/axios'
import { isAdminRole, isModeratorRole } from '@/constants'
import type { Role } from '@/types'

const userRoles = ref<Role[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)
const currentUserId = ref<string | null>(null)

export function useUserRole() {
  const fetchUserRoles = async (userId: string, force = false): Promise<Role[]> => {
    if (loaded.value && !force && currentUserId.value === userId) {
      return userRoles.value
    }

    try {
      loading.value = true
      error.value = null
      const response = await ocs.get<Role[]>(`/users/${userId}/roles`)
      userRoles.value = response.data || []
      currentUserId.value = userId
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
    return userRoles.value.some(isAdminRole)
  })

  const isModerator = computed<boolean>(() => {
    return userRoles.value.some(isModeratorRole)
  })

  const refresh = () => {
    if (currentUserId.value) {
      loaded.value = false
      return fetchUserRoles(currentUserId.value, true)
    }
  }

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
    fetchUserRoles,
    refresh,
    clear,
  }
}
