import { ref, computed } from 'vue'
import { ocs } from '@/axios'

// Shared state - will persist across components
// The API returns an array of headers, each with a nested 'categories' array
const categoryHeaders = ref([])
const loading = ref(false)
const error = ref(null)
const loaded = ref(false)

/**
 * Composable for managing categories
 * Provides shared state across components to avoid redundant API calls
 */
export function useCategories() {
	/**
	 * Fetch categories from the API
	 * Uses cached data if already loaded
	 */
	const fetchCategories = async (force = false) => {
		// Return cached data if already loaded and not forcing refresh
		if (loaded.value && !force) {
			return categoryHeaders.value
		}

		try {
			loading.value = true
			error.value = null

			const response = await ocs.get('/categories')
			categoryHeaders.value = response.data || []
			loaded.value = true

			return categoryHeaders.value
		} catch (e) {
			console.error('Failed to fetch categories:', e)
			error.value = e.message || 'Failed to load categories'
			throw e
		} finally {
			loading.value = false
		}
	}

	/**
	 * Get all categories as a flat list (extracted from all headers)
	 * Useful for sidebar navigation
	 */
	const categoriesList = computed(() => {
		const allCategories = []

		categoryHeaders.value.forEach((header) => {
			if (header.categories && Array.isArray(header.categories)) {
				allCategories.push(...header.categories)
			}
		})

		// Sort by sortOrder
		return allCategories.sort((a, b) => a.sortOrder - b.sortOrder)
	})

	/**
	 * Refresh categories from the API
	 */
	const refresh = () => {
		return fetchCategories(true)
	}

	/**
	 * Clear cached categories
	 */
	const clear = () => {
		categoryHeaders.value = []
		loaded.value = false
		error.value = null
	}

	return {
		// State
		categoryHeaders,
		loading,
		error,
		loaded,

		// Computed
		categoriesList,

		// Methods
		fetchCategories,
		refresh,
		clear,
	}
}
