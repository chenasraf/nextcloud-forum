import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createCurrentUserMock } from '@/test-utils'
import InitializationScreen from './InitializationScreen.vue'

// Uses global mocks for @nextcloud/l10n, NcEmptyContent, NcButton, NcSelect, NcNoteCard, NcLoadingIcon from test-setup.ts

vi.mock('@icons/Cog.vue', () => createIconMock('CogIcon'))

// Uses global mock for @/axios from test-setup.ts
import { ocs } from '@/axios'
const mockOcsGet = vi.mocked(ocs.get)
const mockOcsPost = vi.mocked(ocs.post)

const { mockGetCurrentUser } = createCurrentUserMock()
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => mockGetCurrentUser() }))

describe('InitializationScreen', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockOcsGet.mockResolvedValue({ data: [] })
    mockOcsPost.mockResolvedValue({})
  })

  describe('admin view', () => {
    beforeEach(() => {
      mockGetCurrentUser.mockReturnValue({ uid: 'admin', isAdmin: true })
    })

    it('should render admin view when user is admin', () => {
      const wrapper = mount(InitializationScreen)
      expect(wrapper.find('.nc-empty-content').exists()).toBe(true)
      // NcEmptyContent mock uses "title" prop but component passes "name" prop
      // Check that the init form is rendered for admin users
      expect(wrapper.find('.init-form').exists()).toBe(true)
    })

    it('should show description for admin', () => {
      const wrapper = mount(InitializationScreen)
      expect(wrapper.find('.nc-empty-content .description').text()).toBe(
        'Select the accounts that should have the forum admin role.',
      )
    })

    it('should render select and initialize button', () => {
      const wrapper = mount(InitializationScreen)
      expect(wrapper.find('.nc-select').exists()).toBe(true)
      expect(wrapper.find('button').exists()).toBe(true)
    })

    it('should show initialize button text', () => {
      const wrapper = mount(InitializationScreen)
      const buttons = wrapper.findAll('button')
      const initButton = buttons.find((b) => b.text().includes('Initialize forum'))
      expect(initButton).toBeDefined()
    })

    it('should disable initialize button when no users selected', () => {
      const wrapper = mount(InitializationScreen)
      const buttons = wrapper.findAll('button')
      const initButton = buttons.find((b) => b.text().includes('Initialize forum'))
      expect(initButton?.attributes('disabled')).toBeDefined()
    })

    it('should fetch admin users on mount', () => {
      mount(InitializationScreen)
      expect(mockOcsGet).toHaveBeenCalledWith('/init/admin-users')
    })

    it('should show info note card', () => {
      const wrapper = mount(InitializationScreen)
      const noteCard = wrapper.find('.nc-note-card[data-type="info"]')
      expect(noteCard.exists()).toBe(true)
      expect(noteCard.text()).toBe('All other accounts will receive the default role.')
    })

    it('should show error note card when initialization fails', async () => {
      mockOcsGet.mockResolvedValue({
        data: [{ id: 'admin', displayName: 'Admin' }],
      })
      mockOcsPost.mockRejectedValue({ message: 'Something went wrong' })

      const wrapper = mount(InitializationScreen)
      await vi.dynamicImportSettled()

      // Trigger initialization
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await (wrapper.vm as any).runInitialization()
      await wrapper.vm.$nextTick()

      const errorCard = wrapper.find('.nc-note-card[data-type="error"]')
      expect(errorCard.exists()).toBe(true)
    })

    it('should emit initialized event on successful initialization', async () => {
      mockOcsGet.mockResolvedValue({
        data: [{ id: 'admin', displayName: 'Admin' }],
      })
      mockOcsPost.mockResolvedValue({})

      const wrapper = mount(InitializationScreen)
      await vi.dynamicImportSettled()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await (wrapper.vm as any).runInitialization()

      expect(wrapper.emitted('initialized')).toBeTruthy()
    })

    it('should call post with selected user IDs on initialization', async () => {
      mockOcsGet.mockResolvedValue({
        data: [{ id: 'admin', displayName: 'Admin' }],
      })
      mockOcsPost.mockResolvedValue({})

      const wrapper = mount(InitializationScreen)
      await vi.dynamicImportSettled()

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await (wrapper.vm as any).runInitialization()

      expect(mockOcsPost).toHaveBeenCalledWith('/init/initialize', {
        adminUserIds: ['admin'],
      })
    })
  })

  describe('non-admin view', () => {
    beforeEach(() => {
      mockGetCurrentUser.mockReturnValue({ uid: 'user', isAdmin: false })
    })

    it('should render non-admin view when user is not admin', () => {
      const wrapper = mount(InitializationScreen)
      expect(wrapper.find('.nc-empty-content').exists()).toBe(true)
      // Non-admin view should not show the init form
      expect(wrapper.find('.init-form').exists()).toBe(false)
    })

    it('should show non-admin description', () => {
      const wrapper = mount(InitializationScreen)
      expect(wrapper.find('.nc-empty-content .description').text()).toBe(
        'The forum has not been set up yet. Please contact an administration member to complete the setup.',
      )
    })

    it('should not show select or initialize button', () => {
      const wrapper = mount(InitializationScreen)
      expect(wrapper.find('.nc-select').exists()).toBe(false)
      expect(wrapper.find('.init-form').exists()).toBe(false)
    })

    it('should not fetch admin users', () => {
      mount(InitializationScreen)
      expect(mockOcsGet).not.toHaveBeenCalled()
    })
  })
})
