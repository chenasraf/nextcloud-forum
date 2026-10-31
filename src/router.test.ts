import { describe, it, expect, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
  generateUrl: (path: string) => `/index.php${path}`,
}))

import router from './router'

describe('router', () => {
  it('should resolve forum paths to their routes', () => {
    expect(router.resolve('/t/some-thread').matched).toHaveLength(1)
    expect(router.resolve('/u/alice').matched).toHaveLength(1)
  })

  // @nextcloud/vue only keeps a link as a plain href when its path has no matching route
  it.each(['/apps/spreed/?callUser=alice', '/call/abc123', '/settings/user', '/nonexistent'])(
    'should not match the path %s',
    (path) => {
      expect(router.resolve(path).matched).toHaveLength(0)
    },
  )
})
