import { ref, computed } from 'vue'
import { ocs } from '@/axios'
import type { Thread } from '@/types'

const currentThread = ref<Thread | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)

export function useCurrentThread() {
  const fetchThread = async (idOrSlug: string | number, isSlug: boolean = false): Promise<Thread | null> => {
    try {
      isLoading.value = true
      error.value = null

      const endpoint = isSlug ? `/threads/slug/${idOrSlug}` : `/threads/${idOrSlug}`
      const resp = await ocs.get<Thread>(endpoint)

      currentThread.value = resp.data
      return resp.data
    } catch (e) {
      console.error('Failed to fetch thread', e)
      error.value = (e as Error).message || 'Failed to fetch thread'
      currentThread.value = null
      return null
    } finally {
      isLoading.value = false
    }
  }

  const clearThread = () => {
    currentThread.value = null
    error.value = null
  }

  const categoryId = computed(() => currentThread.value?.categoryId ?? null)

  return {
    currentThread,
    isLoading,
    error,
    categoryId,
    fetchThread,
    clearThread,
  }
}
