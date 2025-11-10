import { ref, computed, type Ref } from 'vue'
import { ocs } from '@/axios'
import type { UserStats } from '@/types'
import { getCurrentUser } from '@nextcloud/auth'

const currentUserStats = ref<UserStats | null>(null)
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

export function useCurrentUser() {
  const fetchCurrentUser = async (force = false): Promise<UserStats | null> => {
    // Don't refetch if already loaded unless forced
    if (loaded.value && !force) {
      return currentUserStats.value
    }

    try {
      loading.value = true
      error.value = null

      const response = await ocs.get<UserStats>('/users/me')
      currentUserStats.value = response.data
      loaded.value = true
      return currentUserStats.value
    } catch (e: unknown) {
      // If 404, user stats don't exist yet (user hasn't posted) - this is OK
      const err = e as { response?: { status?: number } }
      if (err?.response?.status === 404) {
        console.debug('User stats not found - user has not posted yet')
        currentUserStats.value = null
        loaded.value = true
        return null
      }
      console.error('Failed to fetch current user stats', e)
      error.value = (e as Error).message || 'Failed to load user information'
      return null
    } finally {
      loading.value = false
    }
  }

  const refresh = async (): Promise<UserStats | null> => {
    return fetchCurrentUser(true)
  }

  const clear = (): void => {
    currentUserStats.value = null
    loaded.value = false
    error.value = null
  }

  // Get the Nextcloud user info (for display name, avatar, etc.)
  const nextcloudUser = computed(() => getCurrentUser())

  // Computed properties for easy access
  const userId = computed<string | null>(() => nextcloudUser.value?.uid || null)
  const displayName = computed<string>(
    () => nextcloudUser.value?.displayName || nextcloudUser.value?.uid || 'Guest',
  )

  return {
    currentUserStats: currentUserStats as Ref<UserStats | null>,
    loading: loading as Ref<boolean>,
    error: error as Ref<string | null>,
    loaded: loaded as Ref<boolean>,
    userId,
    displayName,
    nextcloudUser,
    fetchCurrentUser,
    refresh,
    clear,
  }
}
