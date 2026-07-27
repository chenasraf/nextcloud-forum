import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'

// Uses global mock for @/axios from test-setup.ts

vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => ({ uid: 'alice', displayName: 'Alice' }),
}))

vi.mock('@icons/ArrowLeft.vue', () => createIconMock('ArrowLeftIcon'))
vi.mock('@icons/Refresh.vue', () => createIconMock('RefreshIcon'))

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

vi.mock('@/components/ThreadCard', () =>
  createComponentMock('ThreadCard', {
    template: '<div class="thread-card-mock" />',
    props: ['thread'],
  }),
)

import ProfileView from '../ProfileView.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)

function createForumUserResponse(overrides: Record<string, unknown> = {}) {
  return {
    userId: 'alice',
    postCount: 5,
    threadCount: 2,
    lastPostAt: null,
    deletedAt: null,
    createdAt: 1700000000,
    updatedAt: 1700000000,
    roles: [],
    ...overrides,
  }
}

function mockProfileResponses(forumUser: Record<string, unknown>) {
  mockOcsGet.mockImplementation((url: string) => {
    if (url === '/users/alice') {
      return Promise.resolve({ data: forumUser })
    }
    // '/users/alice/threads' and '/users/alice/posts'
    return Promise.resolve({ data: [] })
  })
}

const createWrapper = () =>
  mount(ProfileView, {
    global: {
      mocks: {
        $route: { params: { userId: 'alice' } },
        $router: { back: vi.fn(), push: vi.fn() },
      },
    },
  })

describe('ProfileView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows a mailto link when the response includes an email', async () => {
    mockProfileResponses(createForumUserResponse({ email: 'alice@example.com' }))
    const wrapper = createWrapper()
    await flushPromises()

    const link = wrapper.find('a[href="mailto:alice@example.com"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toBe('alice@example.com')
  })

  it('renders no email element when the response has no email', async () => {
    mockProfileResponses(createForumUserResponse())
    const wrapper = createWrapper()
    await flushPromises()

    expect(wrapper.find('a[href^="mailto:"]').exists()).toBe(false)
  })

  it('renders no email element when the email is null', async () => {
    mockProfileResponses(createForumUserResponse({ email: null }))
    const wrapper = createWrapper()
    await flushPromises()

    expect(wrapper.find('a[href^="mailto:"]').exists()).toBe(false)
  })
})
