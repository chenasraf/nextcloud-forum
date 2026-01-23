import { generateOcsUrl } from '@nextcloud/router'
import _axios from '@nextcloud/axios'

const baseURL = generateOcsUrl('/apps/forum/api')
export const http = _axios.create({ baseURL })
export const ocs = _axios.create({ baseURL })
export const webDav = _axios.create({ baseURL: '' })
ocs.interceptors.response.use(
  (response) => {
    const ocsData = response?.data?.ocs?.data
    response.data = ocsData ?? response?.data ?? null
    return response
  },
  (error) => {
    const ocsData = error.response?.data?.ocs?.data
    if (ocsData !== undefined) {
      error.response.data = ocsData
    }
    return Promise.reject(error)
  },
)
