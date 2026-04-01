import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createComponentMock } from '@/test-utils'

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('@nextcloud/dialogs', () => ({
  showSuccess: vi.fn(),
  showError: vi.fn(),
}))

// Mock NcAvatar
vi.mock('@nextcloud/vue/components/NcAvatar', () =>
  createComponentMock('NcAvatar', {
    template: '<span class="nc-avatar-mock" :data-user="user" />',
    props: ['user', 'size', 'showUserStatus'],
  }),
)

// Import after mocks
import { ocs } from '@/axios'
import { showSuccess } from '@nextcloud/dialogs'
import GuestReassignDialog from './GuestReassignDialog.vue'

const mockGet = vi.mocked(ocs.get)
const mockPost = vi.mocked(ocs.post)

describe('GuestReassignDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet.mockResolvedValue({ data: [] } as never)
    mockPost.mockResolvedValue({
      data: { success: true, postsReassigned: 3, threadsReassigned: 1 },
    } as never)
  })

  const createWrapper = (props = {}) => {
    return mount(GuestReassignDialog, {
      props: {
        open: true,
        guestAuthorId: 'guest:abcdef1234567890abcdef1234567890',
        guestDisplayName: 'BrightMountain42',
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders dialog when open', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('does not render dialog when closed', () => {
      const wrapper = createWrapper({ open: false })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('shows description text', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.description').exists()).toBe(true)
      expect(wrapper.text()).toContain('All posts and threads by this guest will be reassigned')
    })

    it('shows user search input', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.user-search').exists()).toBe(true)
    })
  })

  describe('user search', () => {
    it('calls autocomplete API when searching', async () => {
      vi.useFakeTimers()
      const users = [
        { id: 'alice', label: 'Alice Smith' },
        { id: 'bob', label: 'Bob Jones' },
      ]
      mockGet.mockResolvedValue({ data: users } as never)

      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.handleSearch('ali')
      vi.advanceTimersByTime(300)
      await flushPromises()

      expect(mockGet).toHaveBeenCalledWith('/users/autocomplete', {
        params: { search: 'ali', limit: 10 },
      })

      vi.useRealTimers()
    })

    it('does not call API for empty search', async () => {
      vi.useFakeTimers()
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.handleSearch('')
      vi.advanceTimersByTime(300)
      await flushPromises()

      expect(mockGet).not.toHaveBeenCalled()

      vi.useRealTimers()
    })

    it('debounces search calls', async () => {
      vi.useFakeTimers()
      mockGet.mockResolvedValue({ data: [] } as never)

      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.handleSearch('a')
      vm.handleSearch('al')
      vm.handleSearch('ali')
      vi.advanceTimersByTime(300)
      await flushPromises()

      expect(mockGet).toHaveBeenCalledTimes(1)
      expect(mockGet).toHaveBeenCalledWith('/users/autocomplete', {
        params: { search: 'ali', limit: 10 },
      })

      vi.useRealTimers()
    })
  })

  describe('confirm action', () => {
    it('calls reassign API with correct parameters', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      // Simulate selecting a user
      vm.selectedUser = { id: 'alice', label: 'Alice Smith' }
      await wrapper.vm.$nextTick()

      await vm.handleConfirm()
      await flushPromises()

      expect(mockPost).toHaveBeenCalledWith('/admin/guests/reassign', {
        guestAuthorId: 'guest:abcdef1234567890abcdef1234567890',
        targetUserId: 'alice',
      })
    })

    it('shows success message after reassignment', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.selectedUser = { id: 'alice', label: 'Alice Smith' }
      await vm.handleConfirm()
      await flushPromises()

      expect(showSuccess).toHaveBeenCalledWith('Guest posts reassigned successfully')
    })

    it('emits reassigned event on success', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.selectedUser = { id: 'alice', label: 'Alice Smith' }
      await vm.handleConfirm()
      await flushPromises()

      expect(wrapper.emitted('reassigned')).toBeTruthy()
      expect(wrapper.emitted('reassigned')![0]).toEqual([
        {
          guestAuthorId: 'guest:abcdef1234567890abcdef1234567890',
          targetUserId: 'alice',
          targetDisplayName: 'Alice Smith',
        },
      ])
    })

    it('emits update:open false on success', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.selectedUser = { id: 'alice', label: 'Alice Smith' }
      await vm.handleConfirm()
      await flushPromises()

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })

    it('does not call API when no user is selected', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.selectedUser = null
      await vm.handleConfirm()
      await flushPromises()

      expect(mockPost).not.toHaveBeenCalled()
    })

    it('shows error message on API failure', async () => {
      mockPost.mockRejectedValue({
        response: { data: { error: 'Target user does not exist' } },
      })
      vi.spyOn(console, 'error').mockImplementation(() => {})

      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.selectedUser = { id: 'nonexistent', label: 'Nobody' }
      await vm.handleConfirm()
      await flushPromises()

      expect(wrapper.find('.error-message').exists()).toBe(true)
      expect(wrapper.find('.error-message').text()).toBe('Target user does not exist')
    })
  })

  describe('reset on open', () => {
    it('resets state when dialog reopens', async () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      // Set some state
      vm.selectedUser = { id: 'alice', label: 'Alice' }
      vm.error = 'Some error'

      // Close and reopen
      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })

      expect(vm.selectedUser).toBeNull()
      expect(vm.error).toBeNull()
    })
  })

  describe('close event', () => {
    it('emits update:open false when handleClose is called', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as InstanceType<typeof GuestReassignDialog>

      vm.handleClose()

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })
})
