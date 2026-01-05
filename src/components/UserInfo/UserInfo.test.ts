import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createComponentMock } from '@/test-utils'
import { createMockRole } from '@/test-mocks'
import UserInfo from './UserInfo.vue'

vi.mock('@/components/UserAvatar', () =>
  createComponentMock('UserAvatar', {
    template: '<div class="user-avatar-mock" :data-user-id="userId"></div>',
    props: ['userId', 'displayName', 'size', 'isDeleted', 'clickable'],
  }),
)

vi.mock('@/components/RoleBadge', () =>
  createComponentMock('RoleBadge', {
    template: '<span class="role-badge-mock">{{ role.name }}</span>',
    props: ['role', 'density'],
  }),
)

const mockPush = vi.fn()

describe('UserInfo', () => {
  describe('rendering', () => {
    it('should render user avatar', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', displayName: 'Test User' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar-mock').exists()).toBe(true)
    })

    it('should render user name', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', displayName: 'Test User' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-name').text()).toBe('Test User')
    })

    it('should fallback to userId when displayName is empty', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', displayName: '' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-name').text()).toBe('testuser')
    })
  })

  describe('layout', () => {
    it('should apply column layout by default', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-info-component').classes()).not.toContain('layout-inline')
    })

    it('should apply inline layout when specified', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', layout: 'inline' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-info-component').classes()).toContain('layout-inline')
    })
  })

  describe('deleted user', () => {
    it('should apply deleted-user class for deleted users', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', isDeleted: true },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-name').classes()).toContain('deleted-user')
    })

    it('should not be clickable when user is deleted', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', isDeleted: true, clickable: true },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-name').classes()).not.toContain('clickable')
    })
  })

  describe('roles', () => {
    it('should display admin role', () => {
      const adminRole = createMockRole({ id: 1, name: 'Admin', roleType: 'admin' })
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', roles: [adminRole] },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.role-badge-mock').exists()).toBe(true)
      expect(wrapper.find('.role-badge-mock').text()).toBe('Admin')
    })

    it('should display custom roles', () => {
      const customRole = createMockRole({ id: 10, name: 'VIP', roleType: 'custom' })
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', roles: [customRole] },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.role-badge-mock').text()).toBe('VIP')
    })

    it('should hide roles when showRoles is false', () => {
      const adminRole = createMockRole({ id: 1, name: 'Admin', roleType: 'admin' })
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', roles: [adminRole], showRoles: false },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.role-badge-mock').exists()).toBe(false)
    })

    it('should not display default role', () => {
      const defaultRole = createMockRole({ id: 3, name: 'User', roleType: 'default' })
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser', roles: [defaultRole] },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.role-badge-mock').exists()).toBe(false)
    })
  })

  describe('slots', () => {
    it('should render meta slot content', () => {
      const wrapper = mount(UserInfo, {
        props: { userId: 'testuser' },
        slots: {
          meta: '<span class="test-meta">Meta content</span>',
        },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.test-meta').exists()).toBe(true)
    })
  })
})
