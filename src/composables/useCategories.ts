import { ref, type Ref } from 'vue'
import { ocs } from '@/axios'
import type { CategoryHeader } from '@/types'

// Shared state - will persist across components
// The API returns an array of headers, each with a nested 'categories' array
const categoryHeaders = ref<CategoryHeader[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

/**
 * Composable for managing categories
 * Provides shared state across components to avoid redundant API calls
 */
export function useCategories() {
  /**
   * Fetch categories from the API
   * Uses cached data if already loaded
   */
  const fetchCategories = async (force = false): Promise<CategoryHeader[]> => {
    // Return cached data if already loaded and not forcing refresh
    if (loaded.value && !force) {
      return categoryHeaders.value
    }

    try {
      loading.value = true
      error.value = null

      const response = await ocs.get<CategoryHeader[]>('/categories')
      categoryHeaders.value = response.data || []
      loaded.value = true

      return categoryHeaders.value
    } catch (e) {
      console.error('Failed to fetch categories:', e)
      error.value = (e as Error).message || 'Failed to load categories'
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Refresh categories from the API
   */
  const refresh = (): Promise<CategoryHeader[]> => {
    return fetchCategories(true)
  }

  /**
   * Clear cached categories
   */
  const clear = (): void => {
    categoryHeaders.value = []
    loaded.value = false
    error.value = null
  }

  return {
    // State
    categoryHeaders: categoryHeaders as Ref<CategoryHeader[]>,
    loading: loading as Ref<boolean>,
    error: error as Ref<string | null>,
    loaded: loaded as Ref<boolean>,

    // Methods
    fetchCategories,
    refresh,
    clear,
  }
}
