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
                const manualChunks = [
                  'date-fns',
                  'lodash',
                  'dompurify',
                  'linkifyjs',
                  'floating-vue',
                  'focus-trap',
                  'floating-ui',
                  'vue-router',
                  'vue-material-design-icons',
                  'vue',
                  'axios',
                ]
                // Get the part after the last 'node_modules/' to handle pnpm structure
                const parts = id.split('node_modules/')
                const pkgPath = parts[parts.length - 1]

                // Match @nextcloud/xxx packages
                const scopedNextcloudMatch = pkgPath.match(/^@nextcloud\/([^/]+)/)
                if (scopedNextcloudMatch) {
                  return `nextcloud-${scopedNextcloudMatch[1]}`
                }

                // Match nextcloud-xxx packages (without @ scope)
                const nextcloudMatch = pkgPath.match(/^nextcloud-([^/]+)/)
                if (nextcloudMatch) {
                  return `nextcloud-${nextcloudMatch[1]}`
                }

                // Handle other common packages
                for (const chunk of manualChunks) {
                  if (pkgPath.includes(chunk)) {
                    return chunk
                  }
                }

                return 'vendor' // fallback for other deps
              }
            },
          },
        },
      },
    },
  },
)
