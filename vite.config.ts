import { createAppConfig } from '@nextcloud/vite-config'
import path from 'path'
import { visualizer } from 'rollup-plugin-visualizer'

const manualChunksList = [
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

// https://vite.dev/config/
export default createAppConfig(
  {
    app: path.resolve(path.join('src', 'app.ts')),
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
      plugins: [
        visualizer({
          open: process.env.VITE_BUILD_ANALYZE === 'true',
          filename: 'stats.html',
          template: 'treemap',
        }),
      ],
      build: {
        outDir: '../dist',
        manifest: true,
        cssCodeSplit: false,
        rollupOptions: {
          output: {
            entryFileNames: 'js/[name]-[hash].mjs',
            chunkFileNames: 'js/[name]-[hash].mjs',
            assetFileNames: '[ext]/[name]-[hash].[ext]',
            manualChunks(id) {
              if (id.includes('node_modules')) {
                if (id.includes('emoji-mart')) {
                  return 'emoji-mart'
                }

                const parts = id.split('node_modules/')
                const pkgPath = parts[parts.length - 1]

                const scopedNextcloudMatch = pkgPath.match(/^@nextcloud\/([^/]+)/)
                if (scopedNextcloudMatch) {
                  return `nextcloud-${scopedNextcloudMatch[1]}`
                }
                const nextcloudMatch = pkgPath.match(/^nextcloud-([^/]+)/)
                if (nextcloudMatch) {
                  return `nextcloud-${nextcloudMatch[1]}`
                }

                for (const chunk of manualChunksList) {
                  if (pkgPath.includes(chunk)) {
                    return chunk
                  }
                }

                return 'vendor'
              }
            },
          },
        },
      },
    },
  },
)
