import { ref, computed } from 'vue'
import { ocs } from '@/axios'

/**
 * User preference shape mirrored from the backend.
 * Keep in sync with `UserPreferencesService::VALID_KEYS`.
 */
export interface UserPreferences {
  auto_subscribe_created_threads: boolean
  auto_subscribe_replied_threads: boolean
  /** Legacy fallback label for the upload directory (string path). */
  upload_directory: string
  /** Authoritative Nextcloud file ID of the upload directory. May be null on
   *  legacy installs — lazily populated when the user picks via the browser. */
  upload_directory_folder_id: number | null
  /** Server-derived, per-user resolution of the upload directory. Read-only. */
  upload_directory_resolved_path?: string
  signature: string
  hide_edit_history: boolean
  use_category_upload_path: boolean
  upload_behavior: 'configured' | 'prompt'
}

/** Conservative defaults — used while the first fetch is in flight. */
const DEFAULTS: UserPreferences = {
  auto_subscribe_created_threads: true,
  auto_subscribe_replied_threads: false,
  upload_directory: 'Forum',
  upload_directory_folder_id: null,
  upload_directory_resolved_path: 'Forum',
  signature: '',
  hide_edit_history: false,
  use_category_upload_path: true,
  upload_behavior: 'configured',
}

// Module-scoped state: shared across every component that calls the composable.
const preferences = ref<UserPreferences>({ ...DEFAULTS })
const loading = ref<boolean>(false)
const loaded = ref<boolean>(false)
const error = ref<string | null>(null)
let inFlight: Promise<UserPreferences | null> | null = null

/**
 * Composable for the current user's forum preferences.
 *
 * Fetches once on first use and caches in module scope so every consumer
 * (toolbar, prefs view, anything else) reads the same source of truth.
 * Re-fetches are explicit via {@link refresh} or {@link savePreferences}.
 */
export function useUserPreferences() {
  /**
   * Fetch preferences if not already cached. Concurrent callers share the
   * same in-flight promise.
   */
  const fetchUserPreferences = async (force = false): Promise<UserPreferences | null> => {
    if (loaded.value && !force) {
      return preferences.value
    }
    if (inFlight && !force) {
      return inFlight
    }

    loading.value = true
    error.value = null

    inFlight = (async () => {
      try {
        const response = await ocs.get<UserPreferences>('/user-preferences')
        preferences.value = { ...DEFAULTS, ...response.data }
        loaded.value = true
        return preferences.value
      } catch (e) {
        error.value = (e as Error).message || 'Failed to fetch preferences'
        console.error('Failed to fetch user preferences:', e)
        return null
      } finally {
        loading.value = false
        inFlight = null
      }
    })()
    return inFlight
  }

  /**
   * Persist a partial preference update and refresh the cached state with
   * the server's authoritative response.
   */
  const savePreferences = async (
    update: Partial<UserPreferences>,
  ): Promise<UserPreferences | null> => {
    try {
      const response = await ocs.put<UserPreferences>('/user-preferences', update)
      preferences.value = { ...DEFAULTS, ...response.data }
      loaded.value = true
      return preferences.value
    } catch (e) {
      error.value = (e as Error).message || 'Failed to save preferences'
      throw e
    }
  }

  const refresh = () => fetchUserPreferences(true)

  return {
    preferences,
    loading,
    loaded,
    error,
    fetchUserPreferences,
    savePreferences,
    refresh,
    uploadBehavior: computed(() => preferences.value.upload_behavior),
    /** Per-user resolved upload directory — use this for actual uploads. */
    uploadDirectory: computed(
      () => preferences.value.upload_directory_resolved_path ?? preferences.value.upload_directory,
    ),
    useCategoryUploadPath: computed(() => preferences.value.use_category_upload_path),
  }
}
