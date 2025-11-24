import { ref, computed } from 'vue'
import { ocs } from '@/axios'

/**
 * Public forum settings interface
 */
export interface PublicSettings {
  /** Forum title */
  title: string
  /** Forum subtitle */
  subtitle: string
  /** Whether guest access is enabled */
  allow_guest_access: boolean
}

const settings = ref<PublicSettings | null>(null)
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

/**
 * Composable for managing public forum settings
 * Fetches and caches settings that control guest access and forum display
 *
 * @returns Object containing settings state and methods
 *
 * @example
 * ```ts
 * const { allowGuestAccess, fetchPublicSettings, settings } = usePublicSettings()
 * await fetchPublicSettings()
 * if (allowGuestAccess.value) {
 *   // Guest access is enabled
 * }
 * ```
 */
export function usePublicSettings() {
  /**
   * Fetch public settings from the server
   *
   * @param force - If true, bypass cache and fetch fresh data
   * @returns Promise resolving to PublicSettings or null on error
   */
  const fetchPublicSettings = async (force = false): Promise<PublicSettings | null> => {
    if (loaded.value && !force) {
      return settings.value
    }

    try {
      loading.value = true
      error.value = null
      const response = await ocs.get<PublicSettings>('/settings')
      settings.value = response.data
      loaded.value = true
      return settings.value
    } catch (e) {
      error.value = (e as Error).message || 'Failed to fetch public settings'
      console.error('Failed to fetch public settings:', e)
      return null
    } finally {
      loading.value = false
    }
  }

  /**
   * Computed property indicating whether guest access is enabled
   */
  const allowGuestAccess = computed<boolean>(() => {
    return settings.value?.allow_guest_access ?? false
  })

  /**
   * Refresh settings from server, forcing a new fetch
   *
   * @returns Promise resolving to PublicSettings or null on error
   */
  const refresh = () => {
    loaded.value = false
    return fetchPublicSettings(true)
  }

  /**
   * Clear cached settings and reset state
   */
  const clear = () => {
    settings.value = null
    loaded.value = false
    error.value = null
  }

  return {
    /** Current public settings state */
    settings,
    /** Loading state indicator */
    loading,
    /** Error message if fetch failed */
    error,
    /** Whether settings have been loaded at least once */
    loaded,
    /** Computed boolean indicating if guest access is enabled */
    allowGuestAccess,
    /** Fetch settings from server */
    fetchPublicSettings,
    /** Force refresh settings from server */
    refresh,
    /** Clear cached settings */
    clear,
  }
}
