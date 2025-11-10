import { ref } from 'vue'
import type { Ref } from 'vue'
import { ocs } from '@/axios'

interface PermissionCache {
  [key: string]: boolean
}

const categoryPermissions: Ref<PermissionCache> = ref({})

export function usePermissions() {
  const checkCategoryPermission = async (
    categoryId: number,
    permission: string,
  ): Promise<boolean> => {
    const cacheKey = `${categoryId}:${permission}`

    // Return cached result if available
    if (categoryPermissions.value[cacheKey] !== undefined) {
      return categoryPermissions.value[cacheKey]
    }

    try {
      const response = await ocs.get<{ hasPermission: boolean }>(
        `/categories/${categoryId}/permissions/${permission}`,
      )
      const hasPermission = response.data?.hasPermission || false
      categoryPermissions.value[cacheKey] = hasPermission
      return hasPermission
    } catch (e) {
      console.error(`Failed to check permission ${permission} for category ${categoryId}:`, e)
      return false
    }
  }

  const clearCache = () => {
    categoryPermissions.value = {}
  }

  return {
    checkCategoryPermission,
    clearCache,
  }
}
