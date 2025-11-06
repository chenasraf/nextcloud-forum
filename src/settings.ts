import { http } from './axios'
import Settings from './Settings.vue'
import './style.scss'
import { createApp } from 'vue'

console.log('[DEBUG] Mounting Forum Settings')
console.log('[DEBUG] Base URL:', http.defaults.baseURL)
createApp(Settings).mount('#forum-settings')
