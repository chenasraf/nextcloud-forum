import { ref, computed, type Ref } from 'vue'
import { ocs } from '@/axios'
import type { ForumUser } from '@/types'
import { getCurrentUser } from '@nextcloud/auth'

const currentForumUser = ref<ForumUser | null>(null)
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

export function useCurrentUser() {
  const fetchCurrentUser = async (force = false): Promise<ForumUser | null> => {
    // Don't refetch if already loaded unless forced
    if (loaded.value && !force) {
      return currentForumUser.value
    }

    try {
      loading.value = true
      error.value = null

      const response = await ocs.get<ForumUser>('/users/me')
      currentForumUser.value = response.data
      loaded.value = true
      return currentForumUser.value
    } catch (e: unknown) {
      // If 404, forum user doesn't exist yet (user hasn't posted) - this is OK
      const err = e as { response?: { status?: number } }
      if (err?.response?.status === 404) {
        console.debug('Forum user not found - user has not posted yet')
        currentForumUser.value = null
        loaded.value = true
        return null
      }
      console.error('Failed to fetch current forum user', e)
      error.value = (e as Error).message || 'Failed to load user information'
      return null
    } finally {
      loading.value = false
    }
  }

  const refresh = async (): Promise<ForumUser | null> => {
    return fetchCurrentUser(true)
  }

  const clear = (): void => {
    currentForumUser.value = null
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
    currentForumUser: currentForumUser as Ref<ForumUser | null>,
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
