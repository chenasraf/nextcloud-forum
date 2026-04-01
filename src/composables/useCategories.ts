import { ref, type Ref } from 'vue'
import { ocs } from '@/axios'
import type { Category, CategoryHeader } from '@/types'

// Shared state - will persist across components
// The API returns an array of headers, each with a nested 'categories' array
const categoryHeaders = ref<CategoryHeader[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const loaded = ref<boolean>(false)

/**
 * Build a category tree from flat category list.
 * Moves child categories out of the top-level header.categories
 * and nests them under their parent's children array.
 */
function buildCategoryTree(headers: CategoryHeader[]): CategoryHeader[] {
  // Collect all categories into a flat map
  const allCategories: Category[] = []
  for (const header of headers) {
    if (header.categories) {
      for (const cat of header.categories) {
        allCategories.push(cat)
      }
    }
  }

  const catMap = new Map<number, Category>()
  for (const cat of allCategories) {
    cat.children = []
    catMap.set(cat.id, cat)
  }

  // Attach children to parents
  const topLevelIds = new Set<number>()
  for (const cat of allCategories) {
    if (cat.parentId !== null && catMap.has(cat.parentId)) {
      catMap.get(cat.parentId)!.children!.push(cat)
    } else {
      topLevelIds.add(cat.id)
    }
  }

  // Sort children by sortOrder
  for (const cat of allCategories) {
    if (cat.children && cat.children.length > 1) {
      cat.children.sort((a, b) => a.sortOrder - b.sortOrder)
    }
  }

  // Rebuild header.categories to only contain top-level categories
  for (const header of headers) {
    if (header.categories) {
      header.categories = header.categories.filter((cat) => topLevelIds.has(cat.id))
    }
  }

  return headers
}

/**
 * Composable for managing categories
 * Provides shared state across components to avoid redundant API calls
 */
export function useCategories() {
  /**
   * Fetch categories from the API
   * Uses cached data if already loaded
   * @param force - Force refresh even if already loaded
   * @param silent - Don't show loading state during fetch
   */
  const fetchCategories = async (force = false, silent = false): Promise<CategoryHeader[]> => {
    // Return cached data if already loaded and not forcing refresh
    if (loaded.value && !force) {
      return categoryHeaders.value
    }

    try {
      if (!silent) {
        loading.value = true
      }
      error.value = null

      const response = await ocs.get<CategoryHeader[]>('/categories')
      categoryHeaders.value = buildCategoryTree(response.data || [])
      loaded.value = true

      return categoryHeaders.value
    } catch (e) {
      console.error('Failed to fetch categories:', e)
      error.value = (e as Error).message || 'Failed to load categories'
      throw e
    } finally {
      if (!silent) {
        loading.value = false
      }
    }
  }

  /**
   * Refresh categories from the API
   * @param silent - Don't show loading state during fetch
   */
  const refresh = (silent = false): Promise<CategoryHeader[]> => {
    return fetchCategories(true, silent)
  }

  /**
   * Mark a category as read in the local state
   * Updates the readAt timestamp so the category appears read without refetching
   */
  const markCategoryAsRead = (categoryId: number): void => {
    const cat = findCategoryInTree(categoryId)
    if (cat) {
      cat.readAt = Math.floor(Date.now() / 1000)
    }
  }

  /**
   * Find a category by ID in the tree (searches recursively)
   */
  const findCategoryInTree = (categoryId: number): Category | null => {
    for (const header of categoryHeaders.value) {
      if (!header.categories) continue
      const found = findInChildren(header.categories, categoryId)
      if (found) return found
    }
    return null
  }

  /**
   * Get a flat list of all categories across all headers (includes children)
   */
  const getAllCategoriesFlat = (): Category[] => {
    const result: Category[] = []
    for (const header of categoryHeaders.value) {
      if (!header.categories) continue
      collectFlat(header.categories, result)
    }
    return result
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
    markCategoryAsRead,
    findCategoryInTree,
    getAllCategoriesFlat,
  }
}

/** Recursively search for a category by ID */
function findInChildren(categories: Category[], id: number): Category | null {
  for (const cat of categories) {
    if (cat.id === id) return cat
    if (cat.children) {
      const found = findInChildren(cat.children, id)
      if (found) return found
    }
  }
  return null
}

/** Recursively collect all categories into a flat array */
function collectFlat(categories: Category[], result: Category[]): void {
  for (const cat of categories) {
    result.push(cat)
    if (cat.children) {
      collectFlat(cat.children, result)
    }
  }
}
