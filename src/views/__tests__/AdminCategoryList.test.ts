import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { computed, ref } from 'vue'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory } from '@/test-mocks'
import type { Category, CategoryHeader } from '@/types'

// Uses global mock for @/axios from test-setup.ts

// Reactive state for categoryHeaders so tests can control it
const mockCategoryHeaders = ref<CategoryHeader[]>([])
const mockRefresh = vi.fn().mockResolvedValue([])

vi.mock('@/composables/useCategories', () => ({
  useCategories: () => ({
    categoryHeaders: mockCategoryHeaders,
    loading: computed(() => false),
    error: computed(() => null),
    refresh: mockRefresh,
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
vi.mock('@icons/Plus.vue', () => createIconMock('PlusIcon'))
vi.mock('@icons/Pencil.vue', () => createIconMock('PencilIcon'))
vi.mock('@icons/Delete.vue', () => createIconMock('DeleteIcon'))
vi.mock('@icons/ChevronUp.vue', () => createIconMock('ChevronUpIcon'))
vi.mock('@icons/ChevronDown.vue', () => createIconMock('ChevronDownIcon'))
vi.mock('@icons/Information.vue', () => createIconMock('InformationIcon'))

// Mock components
vi.mock('@/components/PageWrapper', () =>
  createComponentMock('PageWrapper', {
    template: '<div class="page-wrapper-mock"><slot name="toolbar" /><slot /></div>',
  }),
)
vi.mock('@/components/PageHeader', () =>
  createComponentMock('PageHeader', {
    template: '<div class="page-header-mock" />',
    props: ['title', 'subtitle'],
  }),
)
vi.mock('@/components/AppToolbar', () =>
  createComponentMock('AppToolbar', {
    template: '<div class="app-toolbar-mock"><slot name="right" /></div>',
  }),
)
vi.mock('@/components/HeaderEditDialog', () =>
  createComponentMock('HeaderEditDialog', {
    template: '<div class="header-edit-dialog-mock" />',
    props: ['open', 'headerId', 'name', 'description', 'sortOrder'],
    emits: ['update:open', 'saved'],
  }),
)

import AdminCategoryList from '../admin/AdminCategoryList.vue'
import { ocs } from '@/axios'

const mockOcsPost = vi.mocked(ocs.post)

function createHeader(id: number, name: string, categories: Category[] = []): CategoryHeader {
  return {
    id,
    name,
    description: null,
    sortOrder: 0,
    createdAt: Date.now(),
    categories,
  }
}

describe('AdminCategoryList', () => {
  const mockRouter = { push: vi.fn() }

  beforeEach(() => {
    vi.clearAllMocks()
    mockCategoryHeaders.value = []
  })

  const createWrapper = () =>
    mount(AdminCategoryList, {
      global: { mocks: { $router: mockRouter, $route: { path: '/admin/categories' } } },
    })

  describe('rendering categories', () => {
    it('should render top-level categories', async () => {
      const cat = createMockCategory({ id: 1, name: 'General', slug: 'general' })
      mockCategoryHeaders.value = [createHeader(1, 'Main', [cat])]

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('General')
      expect(wrapper.text()).toContain('general')
    })

    it('should render subcategories with indentation', async () => {
      const child = createMockCategory({
        id: 2,
        name: 'Sub Category',
        parentId: 1,
        slug: 'sub',
      })
      const parent = createMockCategory({
        id: 1,
        name: 'Parent',
        slug: 'parent',
        children: [child],
      })
      mockCategoryHeaders.value = [createHeader(1, 'Main', [parent])]

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('Parent')
      expect(wrapper.text()).toContain('Sub Category')
      // Subcategory row should have deeper indentation
      const rows = wrapper.findAll('.category-row')
      expect(rows.length).toBeGreaterThanOrEqual(2)
      const subRow = rows.find((r) => r.text().includes('Sub Category'))
      expect(subRow).toBeDefined()
      expect(subRow!.classes()).toContain('subcategory-row')
    })

    it('should render grandchildren (3 levels)', async () => {
      const grandchild = createMockCategory({
        id: 3,
        name: 'Grandchild',
        parentId: 2,
        slug: 'grandchild',
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
      mockCategoryHeaders.value = [createHeader(1, 'Main', [parent])]

      const wrapper = createWrapper()
      await flushPromises()

      expect(wrapper.text()).toContain('Parent')
      expect(wrapper.text()).toContain('Child')
      expect(wrapper.text()).toContain('Grandchild')
    })
  })

  describe('flattenCategoriesWithContext', () => {
    it('should flatten tree with correct depth info', async () => {
      const grandchild = createMockCategory({
        id: 3,
        name: 'GC',
        parentId: 2,
        slug: 'gc',
        children: [],
      })
      const child = createMockCategory({
        id: 2,
        name: 'C',
        parentId: 1,
        slug: 'c',
        children: [grandchild],
      })
      const parent = createMockCategory({
        id: 1,
        name: 'P',
        slug: 'p',
        children: [child],
      })
      mockCategoryHeaders.value = [createHeader(1, 'H', [parent])]

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        flattenCategoriesWithContext: (
          cats: Category[],
          headerId: number,
        ) => Array<{ category: Category; depth: number; index: number; siblings: Category[] }>
      }

      const rows = vm.flattenCategoriesWithContext([parent], 1)
      expect(rows).toHaveLength(3)
      expect(rows[0]!.category.name).toBe('P')
      expect(rows[0]!.depth).toBe(0)
      expect(rows[1]!.category.name).toBe('C')
      expect(rows[1]!.depth).toBe(1)
      expect(rows[2]!.category.name).toBe('GC')
      expect(rows[2]!.depth).toBe(2)
    })

    it('should provide correct sibling references', async () => {
      const child1 = createMockCategory({
        id: 2,
        name: 'C1',
        parentId: 1,
        slug: 'c1',
        children: [],
      })
      const child2 = createMockCategory({
        id: 3,
        name: 'C2',
        parentId: 1,
        slug: 'c2',
        children: [],
      })
      const parent = createMockCategory({
        id: 1,
        name: 'P',
        slug: 'p',
        children: [child1, child2],
      })
      mockCategoryHeaders.value = [createHeader(1, 'H', [parent])]

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        flattenCategoriesWithContext: (
          cats: Category[],
          headerId: number,
        ) => Array<{ category: Category; depth: number; index: number; siblings: Category[] }>
      }

      const rows = vm.flattenCategoriesWithContext([parent], 1)
      // Parent row: siblings is the top-level array
      expect(rows[0]!.siblings).toHaveLength(1)
      // Child rows: siblings is parent.children
      expect(rows[1]!.siblings).toHaveLength(2)
      expect(rows[1]!.index).toBe(0)
      expect(rows[2]!.siblings).toHaveLength(2)
      expect(rows[2]!.index).toBe(1)
    })
  })

  describe('reorder', () => {
    it('should call reorder API when sorting siblings', async () => {
      const child1 = createMockCategory({
        id: 2,
        name: 'C1',
        parentId: 1,
        slug: 'c1',
        children: [],
      })
      const child2 = createMockCategory({
        id: 3,
        name: 'C2',
        parentId: 1,
        slug: 'c2',
        children: [],
      })
      const parent = createMockCategory({
        id: 1,
        name: 'P',
        slug: 'p',
        children: [child1, child2],
      })
      mockCategoryHeaders.value = [createHeader(1, 'H', [parent])]
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      mockOcsPost.mockResolvedValue({ data: { success: true } } as any)

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as {
        reorderSiblings: (siblings: Category[], index: number, amount: number) => Promise<void>
      }

      await vm.reorderSiblings(parent.children!, 0, 1)

      expect(mockOcsPost).toHaveBeenCalledWith('/categories/reorder', {
        categories: expect.arrayContaining([
          expect.objectContaining({ id: 3, sortOrder: 0 }),
          expect.objectContaining({ id: 2, sortOrder: 1 }),
        ]),
      })
    })
  })

  describe('navigation', () => {
    it('should navigate to edit page when clicking edit', async () => {
      const cat = createMockCategory({ id: 5, name: 'Test', slug: 'test' })
      mockCategoryHeaders.value = [createHeader(1, 'H', [cat])]

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as { editCategory: (id: number) => void }
      vm.editCategory(5)

      expect(mockRouter.push).toHaveBeenCalledWith('/admin/categories/5/edit')
    })

    it('should navigate to create page', async () => {
      mockCategoryHeaders.value = [createHeader(1, 'H', [])]

      const wrapper = createWrapper()
      await flushPromises()

      const vm = wrapper.vm as unknown as { createCategory: () => void }
      vm.createCategory()

      expect(mockRouter.push).toHaveBeenCalledWith('/admin/categories/create')
    })
  })
})
