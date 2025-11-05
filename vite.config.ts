import { createAppConfig } from '@nextcloud/vite-config'
import path from 'path'

// https://vite.dev/config/
export default createAppConfig(
  {
    app: path.resolve(path.join('src', 'app.ts')),
    settings: path.resolve(path.join('src', 'settings.ts')),
  },
  {
    config: {
      root: 'src',
      resolve: {
        alias: {
          '@icons': path.resolve(__dirname, 'node_modules/vue-material-design-icons'),
          '@': path.resolve(__dirname, 'src'),
        },
      },
      build: {
        outDir: '../dist',
        cssCodeSplit: false,
        rollupOptions: {
          output: {
            manualChunks(id) {
              if (id.includes('node_modules')) {
                if (id.includes('@nextcloud/dialogs')) return 'nextcloud-dialogs'
                if (id.includes('@nextcloud/vue')) return 'nextcloud-vue'
                if (id.includes('vue')) return 'vue'
                if (id.includes('vue-router')) return 'vue-router'
                if (id.includes('axios')) return 'axios'
                return 'vendor' // fallback for other deps
              }
            },
          },
        },
      },
    },
  },
)
