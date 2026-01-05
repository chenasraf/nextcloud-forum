import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import type { CategoryHeader, Category } from '@/types'

// Mock useCategories composable
const mockFetchCategories = vi.fn()
const mockCategoryHeaders = ref<CategoryHeader[]>([])
vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    categoryHeaders: mockCategoryHeaders,
    fetchCategories: mockFetchCategories,
  }),
}))

// Import after mocks
import MoveCategoryDialog from './MoveCategoryDialog.vue'

describe('MoveCategoryDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockCategoryHeaders.value = []
    mockFetchCategories.mockResolvedValue([])
  })

  const createMockCategory = (overrides: Partial<Category> = {}): Category => ({
    id: 1,
    headerId: 1,
    name: 'Test Category',
    description: null,
    slug: 'test-category',
    sortOrder: 0,
    threadCount: 0,
    postCount: 0,
    createdAt: Date.now(),
    updatedAt: Date.now(),
    ...overrides,
  })

  const createMockHeader = (overrides: Partial<CategoryHeader> = {}): CategoryHeader => ({
    id: 1,
    name: 'Test Header',
    description: null,
    sortOrder: 0,
    createdAt: Date.now(),
    categories: [],
    ...overrides,
  })

  const createWrapper = (props = {}) => {
    return mount(MoveCategoryDialog, {
      props: {
        open: true,
        currentCategoryId: 1,
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

    it('displays the correct title', () => {
      const wrapper = createWrapper()
      const vm = wrapper.vm as unknown as { strings: { title: string } }
      expect(vm.strings.title).toBe('Move thread to category')
    })

    it('displays description text', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Select the category to move this thread to')
    })

    it('renders cancel button', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Cancel')).toBe(true)
    })

    it('renders move button', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button')
      expect(buttons.some((b) => b.text() === 'Move')).toBe(true)
    })
  })

  describe('loading state', () => {
    it('shows loading state while fetching categories', async () => {
      let resolvePromise: (value: unknown) => void
      mockFetchCategories.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePromise = resolve
          }),
      )

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Loading categories')

      resolvePromise!(undefined)
      await flushPromises()

      expect(wrapper.find('.loading-state').exists()).toBe(false)
    })
  })

  describe('error state', () => {
    it('displays error state when fetch fails', async () => {
      mockFetchCategories.mockRejectedValue(new Error('Network error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(wrapper.find('.error-state').exists()).toBe(true)
      expect(wrapper.text()).toContain('Failed to load categories')
    })
  })

  describe('category options', () => {
    it('creates category options from headers', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header 1',
          categories: [
            createMockCategory({ id: 10, name: 'Category A' }),
            createMockCategory({ id: 11, name: 'Category B' }),
          ],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        categoryOptions: Array<{ id: number; name: string; isHeader?: boolean }>
      }

      // Should have 1 header + 2 categories
      expect(vm.categoryOptions.length).toBe(3)
    })

    it('marks headers with negative IDs', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 5,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        categoryOptions: Array<{ id: number; name: string; isHeader?: boolean }>
      }

      const headerOption = vm.categoryOptions.find((o) => o.isHeader)
      expect(headerOption).toBeDefined()
      expect(headerOption!.id).toBe(-5) // Negative of header ID
    })

    it('marks categories with isHeader false', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        categoryOptions: Array<{ id: number; name: string; isHeader?: boolean }>
      }

      const categoryOption = vm.categoryOptions.find((o) => !o.isHeader)
      expect(categoryOption).toBeDefined()
      expect(categoryOption!.isHeader).toBe(false)
    })

    it('indents category names with spaces', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        categoryOptions: Array<{ id: number; name: string; isHeader?: boolean }>
      }

      const categoryOption = vm.categoryOptions.find((o) => !o.isHeader)
      expect(categoryOption!.name).toBe('  Category') // Two spaces prefix
    })

    it('excludes headers with no categories', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({ id: 1, name: 'Empty Header', categories: [] }),
        createMockHeader({
          id: 2,
          name: 'Header with Categories',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        categoryOptions: Array<{ id: number; name: string; isHeader?: boolean }>
      }

      // Should only have header 2 and its category
      expect(vm.categoryOptions.length).toBe(2)
      expect(vm.categoryOptions.some((o) => o.name === 'Empty Header')).toBe(false)
    })
  })

  describe('validation warnings', () => {
    it('shows error when header is selected', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      // Select a header
      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
      }
      vm.selectedCategory = { id: -1, name: 'Header', isHeader: true }

      await flushPromises()

      expect(wrapper.text()).toContain('Cannot move to a category header')
    })

    it('shows warning when same category is selected', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true, currentCategoryId: 10 })
      await flushPromises()

      // Select the same category
      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
      }
      vm.selectedCategory = { id: 10, name: 'Category', isHeader: false }

      await flushPromises()

      expect(wrapper.text()).toContain('This thread is already in this category')
    })
  })

  describe('move button state', () => {
    it('disables move button when no category is selected', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const moveButton = wrapper.findAll('button').find((b) => b.text() === 'Move')
      expect(moveButton!.attributes('disabled')).toBeDefined()
    })

    it('disables move button when header is selected', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
      }
      vm.selectedCategory = { id: -1, name: 'Header', isHeader: true }

      await flushPromises()

      const moveButton = wrapper.findAll('button').find((b) => b.text() === 'Move')
      expect(moveButton!.attributes('disabled')).toBeDefined()
    })

    it('disables move button when same category is selected', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 10, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true, currentCategoryId: 10 })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
      }
      vm.selectedCategory = { id: 10, name: 'Category', isHeader: false }

      await flushPromises()

      const moveButton = wrapper.findAll('button').find((b) => b.text() === 'Move')
      expect(moveButton!.attributes('disabled')).toBeDefined()
    })
  })

  describe('move action', () => {
    it('emits move event with selected category ID', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 20, name: 'Target Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true, currentCategoryId: 10 })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
        handleMove: () => void
      }
      vm.selectedCategory = { id: 20, name: 'Target Category', isHeader: false }

      vm.handleMove()

      expect(wrapper.emitted('move')).toBeTruthy()
      expect(wrapper.emitted('move')![0]).toEqual([20])
    })

    it('sets moving state when move is triggered', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 20, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true, currentCategoryId: 10 })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
        handleMove: () => void
        moving: boolean
      }
      vm.selectedCategory = { id: 20, name: 'Category', isHeader: false }

      vm.handleMove()

      expect(vm.moving).toBe(true)
    })

    it('does not emit move when header is selected', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 20, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
        handleMove: () => void
      }
      vm.selectedCategory = { id: -1, name: 'Header', isHeader: true }

      vm.handleMove()

      expect(wrapper.emitted('move')).toBeFalsy()
    })
  })

  describe('close handling', () => {
    it('emits update:open when cancel button is clicked', async () => {
      const wrapper = createWrapper()
      await flushPromises()

      const cancelButton = wrapper.findAll('button').find((b) => b.text() === 'Cancel')
      await cancelButton!.trigger('click')

      expect(wrapper.emitted('update:open')).toBeTruthy()
      expect(wrapper.emitted('update:open')![0]).toEqual([false])
    })

    it('does not close when moving', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 20, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true, currentCategoryId: 10 })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
        handleMove: () => void
        handleClose: () => void
      }
      vm.selectedCategory = { id: 20, name: 'Category', isHeader: false }

      // Start moving
      vm.handleMove()

      // Try to close
      vm.handleClose()

      // Should not emit close event
      expect(wrapper.emitted('update:open')).toBeFalsy()
    })
  })

  describe('reset method', () => {
    it('resets moving and selectedCategory', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 20, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
        moving: boolean
        reset: () => void
      }

      vm.selectedCategory = { id: 20, name: 'Category', isHeader: false }
      vm.moving = true

      vm.reset()

      expect(vm.selectedCategory).toBeNull()
      expect(vm.moving).toBe(false)
    })
  })

  describe('dialog reopening', () => {
    it('resets selectedCategory when dialog reopens', async () => {
      mockCategoryHeaders.value = [
        createMockHeader({
          id: 1,
          name: 'Header',
          categories: [createMockCategory({ id: 20, name: 'Category' })],
        }),
      ]

      const wrapper = createWrapper({ open: true })
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedCategory: { id: number; name: string; isHeader?: boolean } | null
      }
      vm.selectedCategory = { id: 20, name: 'Category', isHeader: false }

      // Close and reopen
      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })
      await flushPromises()

      expect(vm.selectedCategory).toBeNull()
    })

    it('refetches categories when dialog reopens', async () => {
      const wrapper = createWrapper({ open: true })
      await flushPromises()

      expect(mockFetchCategories).toHaveBeenCalledTimes(1)

      // Close and reopen
      await wrapper.setProps({ open: false })
      await wrapper.setProps({ open: true })
      await flushPromises()

      expect(mockFetchCategories).toHaveBeenCalledTimes(2)
    })
  })
})
