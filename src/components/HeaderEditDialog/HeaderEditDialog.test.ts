import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { CatHeader } from '@/types'

// Mock axios
vi.mock('@/axios', () => ({
  ocs: {
    post: vi.fn(),
    put: vi.fn(),
  },
}))

// Import after mocks
import { ocs } from '@/axios'
import HeaderEditDialog from './HeaderEditDialog.vue'

const mockPost = vi.mocked(ocs.post)
const mockPut = vi.mocked(ocs.put)

describe('HeaderEditDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const createWrapper = (props = {}) => {
    return mount(HeaderEditDialog, {
      props: {
        open: true,
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the dialog when open', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-dialog').exists()).toBe(true)
    })

    it('does not render the dialog when closed', () => {
      const wrapper = createWrapper({ open: false })
      expect(wrapper.find('.nc-dialog').exists()).toBe(false)
    })

    it('shows create title when headerId is null', () => {
      const wrapper = createWrapper({ headerId: null })
      const vm = wrapper.vm as unknown as { isEditing: boolean }
      expect(vm.isEditing).toBe(false)
    })

    it('shows edit title when headerId is provided', () => {
      const wrapper = createWrapper({ headerId: 1 })
      const vm = wrapper.vm as unknown as { isEditing: boolean }
      expect(vm.isEditing).toBe(true)
    })

    it('renders name field', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-text-field').exists()).toBe(true)
    })

    it('renders description field', () => {
      const wrapper = createWrapper()
      expect(wrapper.find('.nc-text-area').exists()).toBe(true)
    })

    it('renders sort order field', () => {
      const wrapper = createWrapper()
      const inputs = wrapper.findAll('.nc-text-field')
      // Name and sort order
      expect(inputs.length).toBe(2)
    })

    it('renders cancel button', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Cancel')).toBe(true)
    })

    it('renders create button when creating', () => {
      const wrapper = createWrapper({ headerId: null })
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Create')).toBe(true)
    })

    it('renders update button when editing', () => {
      const wrapper = createWrapper({ headerId: 1 })
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Update')).toBe(true)
    })
  })

  describe('initial values', () => {
    it('initializes with empty values when creating', () => {
      const wrapper = createWrapper({ headerId: null })
      const vm = wrapper.vm as unknown as {
        localName: string
        localDescription: string
        localSortOrder: number
      }

      expect(vm.localName).toBe('')
      expect(vm.localDescription).toBe('')
      expect(vm.localSortOrder).toBe(0)
    })

    it('initializes with provided values when editing', () => {
      const wrapper = createWrapper({
        headerId: 1,
        name: 'Test Header',
        description: 'Test Description',
        sortOrder: 5,
      })
      const vm = wrapper.vm as unknown as {
        localName: string
        localDescription: string
        localSortOrder: number
      }

      expect(vm.localName).toBe('Test Header')
      expect(vm.localDescription).toBe('Test Description')
      expect(vm.localSortOrder).toBe(5)
    })

    it('resets values when dialog reopens', async () => {
      const wrapper = createWrapper({
        headerId: 1,
        name: 'Original Name',
        description: 'Original Description',
        sortOrder: 3,
      })

      const vm = wrapper.vm as unknown as {
        localName: string
        localDescription: string
        localSortOrder: number
      }

      // Modify local values
      vm.localName = 'Modified Name'
      vm.localDescription = 'Modified Description'
      vm.localSortOrder = 10

      // Close and reopen
      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })

      expect(vm.localName).toBe('Original Name')
      expect(vm.localDescription).toBe('Original Description')
      expect(vm.localSortOrder).toBe(3)
    })
  })

  describe('validation', () => {
    it('disables save button when name is empty', () => {
      const wrapper = createWrapper({ headerId: null, name: '' })
      const vm = wrapper.vm as unknown as { canSave: boolean }
      expect(vm.canSave).toBe(false)
    })

    it('disables save button when name is only whitespace', () => {
      const wrapper = createWrapper({ headerId: null, name: '   ' })
      const vm = wrapper.vm as unknown as { canSave: boolean }
      expect(vm.canSave).toBe(false)
    })

    it('enables save button when name has content', () => {
      const wrapper = createWrapper({ headerId: null, name: 'Valid Name' })
      const vm = wrapper.vm as unknown as { canSave: boolean }
      expect(vm.canSave).toBe(true)
    })
  })

  describe('creating headers', () => {
    it('calls ocs.post when creating a new header', async () => {
      const mockHeader: CatHeader = {
        id: 1,
        name: 'New Header',
        description: 'New Description',
        sortOrder: 0,
        createdAt: Date.now(),
      }
      mockPost.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(mockPost).toHaveBeenCalledWith(
        '/headers',
        expect.objectContaining({
          name: 'New Header',
        }),
      )
    })

    it('emits saved event with new header data', async () => {
      const mockHeader: CatHeader = {
        id: 1,
        name: 'New Header',
        description: null,
        sortOrder: 0,
        createdAt: Date.now(),
      }
      mockPost.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(wrapper.emitted('saved')).toBeTruthy()
      expect(wrapper.emitted('saved')![0]).toEqual([mockHeader])
    })

    it('closes dialog after successful create', async () => {
      const mockHeader: CatHeader = {
        id: 1,
        name: 'New Header',
        description: null,
        sortOrder: 0,
        createdAt: Date.now(),
      }
      mockPost.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })
  })

  describe('updating headers', () => {
    it('calls ocs.put when updating an existing header', async () => {
      const mockHeader: CatHeader = {
        id: 5,
        name: 'Updated Header',
        description: 'Updated Description',
        sortOrder: 2,
        createdAt: Date.now(),
      }
      mockPut.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({
        headerId: 5,
        name: 'Updated Header',
        description: 'Updated Description',
        sortOrder: 2,
      })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(mockPut).toHaveBeenCalledWith(
        '/headers/5',
        expect.objectContaining({
          name: 'Updated Header',
          description: 'Updated Description',
          sortOrder: 2,
        }),
      )
    })

    it('emits saved event with updated header data', async () => {
      const mockHeader: CatHeader = {
        id: 5,
        name: 'Updated Header',
        description: 'Updated Description',
        sortOrder: 2,
        createdAt: Date.now(),
      }
      mockPut.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({
        headerId: 5,
        name: 'Updated Header',
      })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(wrapper.emitted('saved')).toBeTruthy()
      expect(wrapper.emitted('saved')![0]).toEqual([mockHeader])
    })
  })

  describe('error handling', () => {
    it('logs error when save fails', async () => {
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
      mockPost.mockRejectedValue(new Error('Network error'))

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(consoleError).toHaveBeenCalled()
      consoleError.mockRestore()
    })

    it('does not close dialog when save fails', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {})
      mockPost.mockRejectedValue(new Error('Network error'))

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      // Should not emit update:open on failure
      expect(wrapper.emitted('update:open')).toBeFalsy()
    })

    it('resets submitting state after error', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {})
      mockPost.mockRejectedValue(new Error('Network error'))

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      const vm = wrapper.vm as unknown as {
        handleSave: () => Promise<void>
        submitting: boolean
      }
      await vm.handleSave()
      await flushPromises()

      expect(vm.submitting).toBe(false)
    })
  })

  describe('close handling', () => {
    it('emits update:open when cancel button is clicked', async () => {
      const wrapper = createWrapper()

      const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Cancel')
      await cancelButton!.trigger('click')

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })

    it('does not close when submitting', async () => {
      let resolvePromise: (value: unknown) => void
      mockPost.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePromise = resolve
          }) as never,
      )

      const wrapper = createWrapper({ headerId: null, name: 'New Header' })

      // Start submitting
      const vm = wrapper.vm as unknown as {
        handleSave: () => Promise<void>
        handleClose: () => void
      }
      vm.handleSave() // Don't await

      await flushPromises()

      // Try to close while submitting
      vm.handleClose()

      // Should not emit close event
      expect(wrapper.emitted('update:open')).toBeFalsy()

      // Clean up
      resolvePromise!({ data: {} })
      await flushPromises()
    })
  })

  describe('data transformation', () => {
    it('trims name before sending', async () => {
      const mockHeader: CatHeader = {
        id: 1,
        name: 'Trimmed Name',
        description: null,
        sortOrder: 0,
        createdAt: Date.now(),
      }
      mockPost.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({ headerId: null, name: '  Trimmed Name  ' })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(mockPost).toHaveBeenCalledWith(
        '/headers',
        expect.objectContaining({
          name: 'Trimmed Name',
        }),
      )
    })

    it('trims description before sending', async () => {
      const mockHeader: CatHeader = {
        id: 1,
        name: 'Header',
        description: 'Trimmed Description',
        sortOrder: 0,
        createdAt: Date.now(),
      }
      mockPost.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({
        headerId: null,
        name: 'Header',
        description: '  Trimmed Description  ',
      })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(mockPost).toHaveBeenCalledWith(
        '/headers',
        expect.objectContaining({
          description: 'Trimmed Description',
        }),
      )
    })

    it('sends null for empty description', async () => {
      const mockHeader: CatHeader = {
        id: 1,
        name: 'Header',
        description: null,
        sortOrder: 0,
        createdAt: Date.now(),
      }
      mockPost.mockResolvedValue({ data: mockHeader } as never)

      const wrapper = createWrapper({
        headerId: null,
        name: 'Header',
        description: '',
      })

      const vm = wrapper.vm as unknown as { handleSave: () => Promise<void> }
      await vm.handleSave()
      await flushPromises()

      expect(mockPost).toHaveBeenCalledWith(
        '/headers',
        expect.objectContaining({
          description: null,
        }),
      )
    })
  })

  describe('reset method', () => {
    it('resets all local values', () => {
      const wrapper = createWrapper({
        headerId: 1,
        name: 'Header',
        description: 'Description',
        sortOrder: 5,
      })

      const vm = wrapper.vm as unknown as {
        localName: string
        localDescription: string
        localSortOrder: number
        submitting: boolean
        reset: () => void
      }

      vm.reset()

      expect(vm.localName).toBe('')
      expect(vm.localDescription).toBe('')
      expect(vm.localSortOrder).toBe(0)
      expect(vm.submitting).toBe(false)
    })
  })

  describe('prop watchers', () => {
    it('updates localName when name prop changes', async () => {
      const wrapper = createWrapper({ name: 'Initial' })

      await wrapper.setProps({ name: 'Updated' })

      const vm = wrapper.vm as unknown as { localName: string }
      expect(vm.localName).toBe('Updated')
    })

    it('updates localDescription when description prop changes', async () => {
      const wrapper = createWrapper({ description: 'Initial' })

      await wrapper.setProps({ description: 'Updated' })

      const vm = wrapper.vm as unknown as { localDescription: string }
      expect(vm.localDescription).toBe('Updated')
    })

    it('updates localSortOrder when sortOrder prop changes', async () => {
      const wrapper = createWrapper({ sortOrder: 1 })

      await wrapper.setProps({ sortOrder: 10 })

      const vm = wrapper.vm as unknown as { localSortOrder: number }
      expect(vm.localSortOrder).toBe(10)
    })
  })
})
