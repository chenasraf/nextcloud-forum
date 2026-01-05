import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import UserAvatar from './UserAvatar.vue'

// Uses global mock for @nextcloud/vue/components/NcAvatar from test-setup.ts

const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush }),
}))

describe('UserAvatar', () => {
  beforeEach(() => {
    mockPush.mockClear()
  })

  describe('rendering', () => {
    it('should render avatar for active user', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar').exists()).toBe(true)
      expect(wrapper.find('.nc-avatar-mock').exists()).toBe(true)
    })

    it('should pass userId to NcAvatar', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'john_doe' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.nc-avatar-mock').attributes('data-user')).toBe('john_doe')
    })

    it('should pass size to NcAvatar', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser', size: 48 },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.nc-avatar-mock').attributes('data-size')).toBe('48')
    })

    it('should apply height style based on size', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser', size: 64 },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar').attributes('style')).toContain('height: 64px')
    })
  })

  describe('deleted user', () => {
    it('should render differently for deleted user', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'deleteduser', isDeleted: true, displayName: 'Deleted User' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar').exists()).toBe(true)
      expect(wrapper.find('.user-avatar').classes()).not.toContain('clickable')
    })
  })

  describe('clickable behavior', () => {
    it('should have clickable class by default', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar').classes()).toContain('clickable')
    })

    it('should not have clickable class when clickable is false', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser', clickable: false },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar').classes()).not.toContain('clickable')
    })

    it('should not be clickable when user is deleted', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser', isDeleted: true, clickable: true },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.user-avatar').classes()).not.toContain('clickable')
    })

    it('should emit click and navigate when clicked', async () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      await wrapper.find('.user-avatar').trigger('click')
      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')![0]).toEqual(['testuser'])
      expect(mockPush).toHaveBeenCalledWith('/u/testuser')
    })

    it('should not navigate when not clickable', async () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser', clickable: false },
        global: { mocks: { $router: { push: mockPush } } },
      })
      await wrapper.find('.user-avatar').trigger('click')
      expect(wrapper.emitted('click')).toBeFalsy()
      expect(mockPush).not.toHaveBeenCalled()
    })
  })

  describe('default props', () => {
    it('should use default size of 32', () => {
      const wrapper = mount(UserAvatar, {
        props: { userId: 'testuser' },
        global: { mocks: { $router: { push: mockPush } } },
      })
      expect(wrapper.find('.nc-avatar-mock').attributes('data-size')).toBe('32')
    })
  })
})
