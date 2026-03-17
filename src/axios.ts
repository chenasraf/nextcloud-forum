import { generateOcsUrl } from '@nextcloud/router'
import _axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'

const baseURL = generateOcsUrl('/apps/forum/api')
export const http = _axios.create({ baseURL })
export const ocs = _axios.create({ baseURL })
export const webDav = _axios.create({ baseURL: '' })

// Inject guestToken for unauthenticated users
ocs.interceptors.request.use((config) => {
  if (getCurrentUser() === null) {
    const guestToken = localStorage.getItem('forum_guest_token')
    if (guestToken) {
      if (config.method === 'get' || config.method === 'GET') {
        config.params = { ...config.params, guestToken }
      } else {
        config.data = { ...config.data, guestToken }
      }
    }
  }
  return config
})

ocs.interceptors.response.use(
  (response) => {
    const ocsData = response?.data?.ocs?.data
    response.data = ocsData ?? response?.data ?? null
    return response
  },
  (error) => {
    const ocsResponse = error.response?.data?.ocs
    if (ocsResponse !== undefined) {
      // Extract data from OCS response, falling back to meta message for errors
      const ocsData = ocsResponse.data
      const isEmpty =
        ocsData === undefined ||
        ocsData === null ||
        (Array.isArray(ocsData) && ocsData.length === 0)
      if (!isEmpty) {
        error.response.data = ocsData
      } else if (ocsResponse.meta?.message) {
        // For OCS errors that only have meta message (no data)
        error.response.data = { message: ocsResponse.meta.message }
      }
    }
    return Promise.reject(error)
  },
)
