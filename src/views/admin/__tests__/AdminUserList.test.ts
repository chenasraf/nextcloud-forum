import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'

// Uses global mock for @/axios from test-setup.ts

vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))

vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot /></div>',
  }),
)

vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', {
    template: '<div class="page-header-mock">{{ title }}</div>',
    props: ['title', 'subtitle'],
  }),
)

vi.mock('@/components/UserInfo', () =>
  createComponentMock('UserInfo', {
    template: '<div class="user-info-mock" :data-user-id="userId"><slot name="meta" /></div>',
    props: ['userId', 'displayName', 'avatarSize'],
  }),
)

vi.mock('@/components/RoleBadge', () =>
  createComponentMock('RoleBadge', {
    template: '<span class="role-badge-mock">{{ role?.name }}</span>',
    props: ['role', 'density'],
  }),
)

vi.mock('@nextcloud/vue/components/NcActions', () => ({
  default: {
    name: 'NcActions',
    template: '<div class="nc-actions-mock"><slot /></div>',
    props: ['variant'],
  },
}))

vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
  default: {
    name: 'NcActionButton',
    template:
      '<button class="nc-action-button-mock" @click="$emit(\'click\')"><slot name="icon" /></button>',
    props: ['ariaLabel', 'title'],
  },
}))

import AdminUserList from '../AdminUserList.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)

function createAdminUser(overrides: Record<string, unknown> = {}) {
  return {
    userId: 'alice',
    displayName: 'Alice',
    email: 'alice@example.com',
    postCount: 5,
    threadCount: 2,
    createdAt: 1700000000,
    updatedAt: 1700000000,
    deletedAt: null,
    isDeleted: false,
    roles: [],
    ...overrides,
  }
}

function mockUsersResponse(users: unknown[]) {
  mockOcsGet.mockImplementation((url: string) => {
    if (url === '/admin/users') {
      return Promise.resolve({ data: { users } })
    }
    // '/roles'
    return Promise.resolve({ data: [] })
  })
}

describe('AdminUserList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows the Email column header', async () => {
    mockUsersResponse([createAdminUser()])
    const wrapper = mount(AdminUserList)
    await flushPromises()

    expect(wrapper.find('.header-row').text()).toContain('Email')
  })

  it('renders the email as a mailto link', async () => {
    mockUsersResponse([createAdminUser()])
    const wrapper = mount(AdminUserList)
    await flushPromises()

    const link = wrapper.find('a[href="mailto:alice@example.com"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toBe('alice@example.com')
  })

  it('shows a dash when the user has no email', async () => {
    mockUsersResponse([createAdminUser({ userId: 'bob', displayName: 'Bob', email: null })])
    const wrapper = mount(AdminUserList)
    await flushPromises()

    expect(wrapper.find('a[href^="mailto:"]').exists()).toBe(false)
    expect(wrapper.find('.col-email .muted').text()).toBe('—')
  })
})
