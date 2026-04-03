import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory } from '@/test-mocks'

// Uses global mock for @/axios from test-setup.ts

// Mock usePermissions composable
const mockCheckCategoryPermission = vi.fn()
vi.mock('@/composables/usePermissions', () => ({
  usePermissions: () => ({
    checkCategoryPermission: mockCheckCategoryPermission,
  }),
}))

// Mock icons
vi.mock('@icons/ArrowLeft.vue', () => createIconMock('ArrowLeftIcon'))

// Mock components
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot name="toolbar" /><slot /></div>',
  }),
)

vi.mock('@/components/AppToolbar', () =>
  createComponentMock('AppToolbar', {
    template: '<div class="app-toolbar-mock"><slot name="left" /><slot name="right" /></div>',
  }),
)

vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', {
    template: '<div class="page-header-mock" />',
    props: ['title', 'subtitle'],
  }),
)

vi.mock('@/components/ThreadCreateForm', () =>
  createComponentMock('ThreadCreateForm', {
    template: '<div class="thread-create-form-mock" />',
    props: ['draftStatus'],
    emits: ['submit', 'cancel', 'update:title', 'update:content'],
  }),
)

// Uses global mock for @nextcloud/dialogs from test-setup.ts

import CreateThreadView from '../CreateThreadView.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)

describe('CreateThreadView', () => {
  const mockCategory = createMockCategory({ id: 7, slug: 'tech', name: 'Technology' })

  const mockRouter = {
    push: vi.fn(),
  }

  const mockRoute = {
    params: { categorySlug: 'tech' },
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const mockGetResponse = (data: Record<string, unknown>): Promise<any> => Promise.resolve(data)

  beforeEach(() => {
    vi.clearAllMocks()
    mockCheckCategoryPermission.mockResolvedValue(true)
    mockOcsGet.mockImplementation((url: string) => {
      if (url.includes('/categories/slug/')) {
        return mockGetResponse({ data: mockCategory })
      }
      if (url.includes('/drafts/')) {
        return mockGetResponse({ data: { draft: null } })
      }
      return mockGetResponse({ data: null })
    })
  })

  const createWrapper = () => {
    return mount(CreateThreadView, {
      global: {
        mocks: {
          $router: mockRouter,
          $route: mockRoute,
        },
      },
    })
  }

  describe('canPost permission', () => {
    it('renders form when canPost returns true', async () => {
      mockCheckCategoryPermission.mockResolvedValue(true)
      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.thread-create-form-mock').exists()).toBe(true)
    })

    it('shows permission denied error when canPost returns false', async () => {
      mockCheckCategoryPermission.mockResolvedValue(false)
      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.find('.thread-create-form-mock').exists()).toBe(false)
      expect(wrapper.text()).toContain(
        'You do not have permission to create threads in this category.',
      )
    })

    it('checks permission after category is fetched', async () => {
      createWrapper()
      await flushPromises()

      expect(mockCheckCategoryPermission).toHaveBeenCalledWith(7, 'canPost')
    })
  })
})
