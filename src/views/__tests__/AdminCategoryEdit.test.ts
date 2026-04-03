import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory, createMockRole } from '@/test-mocks'
import type { Category, CategoryHeader, Role } from '@/types'

// Uses global mock for @/axios from test-setup.ts

// Reactive state for categories
const mockCategoryHeaders = ref<CategoryHeader[]>([])
const mockFetchCategories = vi.fn().mockResolvedValue([])
const mockRefresh = vi.fn().mockResolvedValue([])
const mockGetAllFlat = vi.fn().mockReturnValue([])

vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    categoryHeaders: mockCategoryHeaders,
    fetchCategories: mockFetchCategories,
    refresh: mockRefresh,
    getAllCategoriesFlat: mockGetAllFlat,
  }),
}))

// Mock NcCheckboxRadioSwitch (imports .css that Vitest can't handle)
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template: '<label class="nc-checkbox"><input type="checkbox" /><slot /></label>',
    props: ['modelValue', 'disabled', 'value', 'type', 'name'],
    emits: ['update:model-value'],
  },
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
    template: '<div class="app-toolbar-mock"><slot name="left" /></div>',
  }),
)
vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', {
    template: '<div class="page-header-mock" />',
    props: ['title', 'subtitle'],
  }),
)
vi.mock('@/components/FormSection', () =>
  createComponentMock('FormSection', {
    template: '<div class="form-section-mock"><slot /></div>',
    props: ['title', 'subtitle'],
  }),
)
vi.mock('@/components/CategoryCard', () =>
  createComponentMock('CategoryCard', {
    template: '<div class="category-card-mock" />',
    props: ['category'],
  }),
)
vi.mock('@/components/ColorPickerPreset', () =>
  createComponentMock('ColorPickerPreset', {
    template: '<div class="color-picker-mock" />',
    props: ['modelValue', 'presets', 'label'],
    emits: ['update:modelValue'],
  }),
)

import AdminCategoryEdit from '../admin/AdminCategoryEdit.vue'
import { ocs } from '@/axios'

const mockOcsGet = vi.mocked(ocs.get)
const mockOcsPost = vi.mocked(ocs.post)
const mockOcsPut = vi.mocked(ocs.put)

function createHeader(id: number, name: string, categories: Category[] = []): CategoryHeader {
  return { id, name, description: null, sortOrder: 0, createdAt: Date.now(), categories }
}

describe('AdminCategoryEdit', () => {
  const mockRouter = { push: vi.fn() }
  const defaultRoles: Role[] = [
    createMockRole({ id: 1, name: 'Admin', roleType: 'admin', isSystemRole: true }),
    createMockRole({ id: 2, name: 'Moderator', roleType: 'moderator', isSystemRole: true }),
    createMockRole({ id: 3, name: 'Member', roleType: 'default', isSystemRole: true }),
    createMockRole({ id: 4, name: 'Guest', roleType: 'guest', isSystemRole: true }),
  ]

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const mockResponse = (data: unknown): Promise<any> => Promise.resolve({ data })

  beforeEach(() => {
    vi.clearAllMocks()
    mockCategoryHeaders.value = []
    mockFetchCategories.mockResolvedValue([])
    mockGetAllFlat.mockReturnValue([])
  })

  const createWrapper = (routeParams: Record<string, string> = {}) =>
    mount(AdminCategoryEdit, {
      global: {
        mocks: {
          $router: mockRouter,
          $route: { params: routeParams, path: '/admin/categories/create' },
        },
      },
    })

  const setupCreateMocks = () => {
    mockOcsGet.mockImplementation((url: string) => {
      if (url === '/roles') return mockResponse(defaultRoles)
      if (url === '/teams') return mockResponse([])
      return mockResponse(null)
    })
  }

  const setupEditMocks = (category: Category) => {
    mockOcsGet.mockImplementation((url: string) => {
      if (url === '/roles') return mockResponse(defaultRoles)
      if (url === '/teams') return mockResponse([])
      if (url === `/categories/${category.id}`) return mockResponse(category)
      if (url === `/categories/${category.id}/permissions`) return mockResponse([])
      return mockResponse(null)
    })
  }

  describe('parent dropdown', () => {
    it('should include headers as parent options', async () => {
      mockCategoryHeaders.value = [createHeader(1, 'General'), createHeader(2, 'Support')]
      setupCreateMocks()

      const wrapper = createWrapper()
      await flushPromises()

      type VM = { parentOptions: Array<{ id: string; label: string; type: string }> }
      const vm = wrapper.vm as unknown as VM

      const headerOptions = vm.parentOptions.filter((o) => o.type === 'header')
      expect(headerOptions).toHaveLength(2)
      expect(headerOptions[0]!.label).toBe('General')
      expect(headerOptions[1]!.label).toBe('Support')
    })

    it('should include categories nested under headers', async () => {
      const cat = createMockCategory({ id: 10, name: 'Announcements', slug: 'ann' })
      mockCategoryHeaders.value = [createHeader(1, 'General', [cat])]
      setupCreateMocks()

      const wrapper = createWrapper()
      await flushPromises()

      type VM = { parentOptions: Array<{ id: string; label: string; type: string }> }
      const vm = wrapper.vm as unknown as VM

      const catOptions = vm.parentOptions.filter((o) => o.type === 'category')
      expect(catOptions).toHaveLength(1)
      expect(catOptions[0]!.id).toBe('category:10')
    })

    it('should exclude the current category and its descendants when editing', async () => {
      const grandchild = createMockCategory({
        id: 3,
        name: 'GC',
        parentId: 2,
        slug: 'gc',
        children: [],
      })
      const child = createMockCategory({
        id: 2,
        name: 'Child',
        parentId: 1,
        slug: 'child',
        children: [grandchild],
      })
      const parent = createMockCategory({
        id: 1,
        name: 'Parent',
        slug: 'parent',
        children: [child],
      })
      const sibling = createMockCategory({
        id: 4,
        name: 'Sibling',
        slug: 'sibling',
        children: [],
      })
      mockCategoryHeaders.value = [createHeader(1, 'H', [parent, sibling])]

      // getAllCategoriesFlat returns the flat list for descendant collection
      mockGetAllFlat.mockReturnValue([parent, child, grandchild, sibling])

      const editCategory = createMockCategory({
        id: 1,
        headerId: 1,
        name: 'Parent',
        slug: 'parent',
      })
      setupEditMocks(editCategory)

      const wrapper = createWrapper({ id: '1' })
      await flushPromises()

      type VM = { parentOptions: Array<{ id: string; label: string; type: string }> }
      const vm = wrapper.vm as unknown as VM

      const catOptions = vm.parentOptions.filter((o) => o.type === 'category')
      const catIds = catOptions.map((o) => o.id)

      // Should exclude category 1 (self), 2 (child), 3 (grandchild)
      expect(catIds).not.toContain('category:1')
      expect(catIds).not.toContain('category:2')
      expect(catIds).not.toContain('category:3')
      // Should include sibling
      expect(catIds).toContain('category:4')
    })
  })

  describe('form submission', () => {
    it('should send parentId when a category parent is selected', async () => {
      const cat = createMockCategory({ id: 10, name: 'Parent Cat', slug: 'parent-cat' })
      mockCategoryHeaders.value = [createHeader(1, 'H', [cat])]
      setupCreateMocks()
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      mockOcsPost.mockResolvedValue({ data: { id: 99 } } as any)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedParent: { id: string; label: string; type: string } | null
        formData: {
          name: string
          slug: string
          parentId: number | null
          headerId: number | null
        }
        submitForm: () => Promise<void>
      }

      vm.selectedParent = { id: 'category:10', label: 'Parent Cat', type: 'category' }
      vm.formData.parentId = 10
      vm.formData.headerId = null
      vm.formData.name = 'New Child'
      vm.formData.slug = 'new-child'
      await wrapper.vm.$nextTick()

      await vm.submitForm()
      await flushPromises()

      expect(mockOcsPost).toHaveBeenCalledWith(
        '/categories',
        expect.objectContaining({
          parentId: 10,
          headerId: null,
          name: 'New Child',
          slug: 'new-child',
        }),
      )
    })

    it('should send headerId when a header parent is selected', async () => {
      mockCategoryHeaders.value = [createHeader(1, 'General')]
      setupCreateMocks()
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      mockOcsPost.mockResolvedValue({ data: { id: 99 } } as any)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedParent: { id: string; label: string; type: string } | null
        formData: {
          name: string
          slug: string
          parentId: number | null
          headerId: number | null
        }
        submitForm: () => Promise<void>
      }

      vm.selectedParent = { id: 'header:1', label: 'General', type: 'header' }
      vm.formData.headerId = 1
      vm.formData.parentId = null
      vm.formData.name = 'New Category'
      vm.formData.slug = 'new-category'
      await wrapper.vm.$nextTick()

      await vm.submitForm()
      await flushPromises()

      expect(mockOcsPost).toHaveBeenCalledWith(
        '/categories',
        expect.objectContaining({
          headerId: 1,
          parentId: null,
          name: 'New Category',
        }),
      )
    })

    it('should send hideChildrenOnCard in the payload', async () => {
      mockCategoryHeaders.value = [createHeader(1, 'H')]
      setupCreateMocks()
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      mockOcsPost.mockResolvedValue({ data: { id: 99 } } as any)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        selectedParent: { id: string; label: string; type: string } | null
        formData: {
          name: string
          slug: string
          headerId: number | null
          parentId: number | null
          hideChildrenOnCard: boolean
        }
        submitForm: () => Promise<void>
      }

      vm.selectedParent = { id: 'header:1', label: 'H', type: 'header' }
      vm.formData.headerId = 1
      vm.formData.parentId = null
      vm.formData.name = 'Test'
      vm.formData.slug = 'test'
      vm.formData.hideChildrenOnCard = true
      await wrapper.vm.$nextTick()

      await vm.submitForm()
      await flushPromises()

      expect(mockOcsPost).toHaveBeenCalledWith(
        '/categories',
        expect.objectContaining({ hideChildrenOnCard: true }),
      )
    })

    it('should use PUT when editing an existing category', async () => {
      const existingCat = createMockCategory({
        id: 5,
        headerId: 1,
        name: 'Existing',
        slug: 'existing',
      })
      mockCategoryHeaders.value = [createHeader(1, 'H', [existingCat])]
      setupEditMocks(existingCat)
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      mockOcsPut.mockResolvedValue({ data: existingCat } as any)
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      mockOcsPost.mockResolvedValue({ data: { success: true } } as any)

      const wrapper = createWrapper({ id: '5' })
      await flushPromises()

      const vm = wrapper.vm as unknown as { submitForm: () => Promise<void> }
      await vm.submitForm()
      await flushPromises()

      expect(mockOcsPut).toHaveBeenCalledWith('/categories/5', expect.any(Object))
    })
  })

  describe('navigation', () => {
    it('should navigate back to category list on cancel', async () => {
      mockCategoryHeaders.value = []
      setupCreateMocks()

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as { goBack: () => void }
      vm.goBack()

      expect(mockRouter.push).toHaveBeenCalledWith('/admin/categories')
    })
  })
})
