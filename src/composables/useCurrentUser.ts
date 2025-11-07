import { ref, computed, type Ref } from 'vue'
import { ocs } from '@/axios'
import type { ForumUser } from '@/types'
import { getCurrentUser } from '@nextcloud/auth'

const currentUser = ref<ForumUser | null>(null)
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

export function useCurrentUser() {
	const fetchCurrentUser = async (force = false): Promise<ForumUser | null> => {
		// Don't refetch if already loaded unless forced
		if (loaded.value && !force) {
			return currentUser.value
		}

		try {
			loading.value = true
			error.value = null

			const response = await ocs.get<ForumUser>('/current-user')
			currentUser.value = response.data
			loaded.value = true
			return currentUser.value
		} catch (e: any) {
			// If 404, user hasn't been created yet - this is OK, we'll use Nextcloud user info
			if (e?.response?.status === 404) {
				console.debug('Forum user not found, will be created on first post')
				currentUser.value = null
				loaded.value = true
				return null
			}
			console.error('Failed to fetch current user', e)
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
		currentUser.value = null
		loaded.value = false
		error.value = null
	}

	// Get the Nextcloud user info (for display name, avatar, etc.)
	const nextcloudUser = computed(() => getCurrentUser())

	// Computed properties for easy access
	const userId = computed<string | null>(() => nextcloudUser.value?.uid || null)
	const displayName = computed<string>(() => nextcloudUser.value?.displayName || nextcloudUser.value?.uid || 'Guest')

	return {
		currentUser: currentUser as Ref<ForumUser | null>,
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
