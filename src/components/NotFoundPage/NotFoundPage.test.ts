import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock } from '@/test-utils'
import NotFoundPage from './NotFoundPage.vue'

// Uses global mocks for @nextcloud/l10n, @nextcloud/router, NcButton, NcEmptyContent from test-setup.ts

vi.mock('@icons/ArrowLeft.vue', () => createIconMock('ArrowLeftIcon'))
vi.mock('@icons/Home.vue', () => createIconMock('HomeIcon'))
vi.mock('@icons/AlertCircle.vue', () => createIconMock('AlertCircleIcon'))

const mockBack = vi.fn()
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ back: mockBack, push: mockPush }),
}))

describe('NotFoundPage', () => {
  beforeEach(() => {
    mockBack.mockClear()
    mockPush.mockClear()
    Object.defineProperty(window, 'history', {
      value: { length: 5 },
      writable: true,
    })
  })

  describe('rendering', () => {
    it('should render with default props', () => {
      const wrapper = mount(NotFoundPage)
      expect(wrapper.find('.not-found-page').exists()).toBe(true)
      expect(wrapper.find('.nc-empty-content').exists()).toBe(true)
    })

    it('should display default title', () => {
      const wrapper = mount(NotFoundPage)
      expect(wrapper.find('.title').text()).toBe('Page not found')
    })

    it('should display default description', () => {
      const wrapper = mount(NotFoundPage)
      expect(wrapper.find('.description').text()).toBe(
        'The page you are looking for could not be found.',
      )
    })

    it('should display custom title', () => {
      const wrapper = mount(NotFoundPage, {
        props: { title: 'Custom Title' },
      })
      expect(wrapper.find('.title').text()).toBe('Custom Title')
    })

    it('should display custom description', () => {
      const wrapper = mount(NotFoundPage, {
        props: { description: 'Custom description text' },
      })
      expect(wrapper.find('.description').text()).toBe('Custom description text')
    })
  })

  describe('buttons', () => {
    it('should show back button by default', () => {
      const wrapper = mount(NotFoundPage)
      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBeGreaterThanOrEqual(1)
      expect(wrapper.find('.arrow-left-icon').exists()).toBe(true)
    })

    it('should show home button by default', () => {
      const wrapper = mount(NotFoundPage)
      expect(wrapper.find('.home-icon').exists()).toBe(true)
    })

    it('should hide back button when showBackButton is false', () => {
      const wrapper = mount(NotFoundPage, {
        props: { showBackButton: false },
      })
      expect(wrapper.find('.arrow-left-icon').exists()).toBe(false)
    })

    it('should hide home button when showHomeButton is false', () => {
      const wrapper = mount(NotFoundPage, {
        props: { showHomeButton: false },
      })
      expect(wrapper.find('.home-icon').exists()).toBe(false)
    })

    it('should have correct home URL', () => {
      const wrapper = mount(NotFoundPage)
      const homeButton = wrapper.findAll('button').find((b) => b.find('.home-icon').exists())
      expect(homeButton?.attributes('href')).toBe('/apps/forum')
    })
  })

  describe('navigation', () => {
    it('should go back when back button is clicked and history exists', async () => {
      Object.defineProperty(window, 'history', {
        value: { length: 5 },
        writable: true,
      })
      const wrapper = mount(NotFoundPage)
      const backButton = wrapper.findAll('button').find((b) => b.find('.arrow-left-icon').exists())
      await backButton?.trigger('click')
      expect(mockBack).toHaveBeenCalled()
    })

    it('should navigate to home when back button is clicked and no history', async () => {
      Object.defineProperty(window, 'history', {
        value: { length: 1 },
        writable: true,
      })
      const wrapper = mount(NotFoundPage)
      const backButton = wrapper.findAll('button').find((b) => b.find('.arrow-left-icon').exists())
      await backButton?.trigger('click')
      expect(mockPush).toHaveBeenCalledWith('/')
    })
  })

  describe('icon', () => {
    it('should render default AlertCircle icon', () => {
      const wrapper = mount(NotFoundPage)
      expect(wrapper.find('.alert-circle-icon').exists()).toBe(true)
    })
  })
})
