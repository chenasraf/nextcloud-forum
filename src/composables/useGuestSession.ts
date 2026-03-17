import { ref, computed } from 'vue'
import { ocs } from '@/axios'
import { getCurrentUser } from '@nextcloud/auth'

const STORAGE_TOKEN_KEY = 'forum_guest_token'
const STORAGE_DISPLAY_NAME_KEY = 'forum_guest_display_name'

const guestDisplayName = ref<string | null>(null)
const identityLoaded = ref(false)

function getOrCreateToken(): string {
  let token = localStorage.getItem(STORAGE_TOKEN_KEY)
  if (token && /^[0-9a-f]{32}$/.test(token)) {
    return token
  }

  // Generate 32-char hex token
  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  token = Array.from(bytes)
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('')
  localStorage.setItem(STORAGE_TOKEN_KEY, token)
  return token
}

export function useGuestSession() {
  const isGuest = computed(() => getCurrentUser() === null)
  const guestToken = computed(() => (isGuest.value ? getOrCreateToken() : null))

  const fetchGuestIdentity = async (): Promise<void> => {
    if (!isGuest.value || !guestToken.value) {
      return
    }

    // Check localStorage cache first
    const cached = localStorage.getItem(STORAGE_DISPLAY_NAME_KEY)
    if (cached) {
      guestDisplayName.value = cached
      identityLoaded.value = true
      return
    }

    try {
      const response = await ocs.get<{ displayName: string; guestToken: string; isGuest: true }>(
        '/guest/me',
        { params: { guestToken: guestToken.value } },
      )

      if (response.data) {
        guestDisplayName.value = response.data.displayName
        identityLoaded.value = true
        localStorage.setItem(STORAGE_DISPLAY_NAME_KEY, response.data.displayName)
      }
    } catch (e) {
      console.error('Failed to fetch guest identity', e)
    }
  }

  return {
    isGuest,
    guestToken,
    guestDisplayName,
    identityLoaded,
    fetchGuestIdentity,
  }
}
