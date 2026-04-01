import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createMockCategory } from '@/test-mocks'
import type { CategoryHeader } from '@/types'

// Mock the axios module before importing the composable
vi.mock('@/axios', () => ({
  ocs: {
    get: vi.fn(),
  },
}))

import { useCategories } from '../useCategories'
import { ocs } from '@/axios'

describe('useCategories', () => {
  beforeEach(() => {
    const { clear } = useCategories()
    clear()
    vi.clearAllMocks()
  })

  describe('tree building', () => {
    it('should build tree from flat categories', async () => {
      const parent = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'Parent' })
      const child1 = createMockCategory({
        id: 2,
        headerId: null,
        parentId: 1,
        name: 'Child 1',
        sortOrder: 0,
      })
      const child2 = createMockCategory({
        id: 3,
        headerId: null,
        parentId: 1,
        name: 'Child 2',
        sortOrder: 1,
      })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [parent, child1, child2],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, categoryHeaders } = useCategories()
      await fetchCategories(true)

      // Top-level should only have parent
      expect(categoryHeaders.value).toHaveLength(1)
      expect(categoryHeaders.value[0]!.categories).toHaveLength(1)
      expect(categoryHeaders.value[0]!.categories![0]!.name).toBe('Parent')

      // Parent should have children
      const parentCat = categoryHeaders.value[0]!.categories![0]!
      expect(parentCat.children).toHaveLength(2)
      expect(parentCat.children![0]!.name).toBe('Child 1')
      expect(parentCat.children![1]!.name).toBe('Child 2')
    })

    it('should sort children by sortOrder', async () => {
      const parent = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'Parent' })
      const child1 = createMockCategory({
        id: 2,
        headerId: null,
        parentId: 1,
        name: 'Second',
        sortOrder: 2,
      })
      const child2 = createMockCategory({
        id: 3,
        headerId: null,
        parentId: 1,
        name: 'First',
        sortOrder: 1,
      })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [parent, child1, child2],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, categoryHeaders } = useCategories()
      await fetchCategories(true)

      const parentCat = categoryHeaders.value[0]!.categories![0]!
      expect(parentCat.children![0]!.name).toBe('First')
      expect(parentCat.children![1]!.name).toBe('Second')
    })

    it('should handle categories with no children', async () => {
      const cat = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'Standalone' })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [cat],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, categoryHeaders } = useCategories()
      await fetchCategories(true)

      expect(categoryHeaders.value[0]!.categories![0]!.children).toEqual([])
    })

    it('should build a 3-level deep tree (grandchildren)', async () => {
      const grandparent = createMockCategory({
        id: 1,
        headerId: 1,
        parentId: null,
        name: 'Grandparent',
      })
      const parent = createMockCategory({
        id: 2,
        headerId: null,
        parentId: 1,
        name: 'Parent',
      })
      const child = createMockCategory({
        id: 3,
        headerId: null,
        parentId: 2,
        name: 'Child',
      })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [grandparent, parent, child],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, categoryHeaders } = useCategories()
      await fetchCategories(true)

      // Only grandparent at top level
      expect(categoryHeaders.value[0]!.categories).toHaveLength(1)
      const gp = categoryHeaders.value[0]!.categories![0]!
      expect(gp.name).toBe('Grandparent')

      // Parent nested under grandparent
      expect(gp.children).toHaveLength(1)
      expect(gp.children![0]!.name).toBe('Parent')

      // Child nested under parent
      expect(gp.children![0]!.children).toHaveLength(1)
      expect(gp.children![0]!.children![0]!.name).toBe('Child')
    })
  })

  describe('getAllCategoriesFlat', () => {
    it('should return all categories including children', async () => {
      const parent = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'Parent' })
      const child = createMockCategory({ id: 2, headerId: null, parentId: 1, name: 'Child' })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [parent, child],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, getAllCategoriesFlat } = useCategories()
      await fetchCategories(true)

      const flat = getAllCategoriesFlat()
      expect(flat).toHaveLength(2)
      expect(flat.map((c) => c.name)).toContain('Parent')
      expect(flat.map((c) => c.name)).toContain('Child')
    })

    it('should include deeply nested categories', async () => {
      const gp = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'GP' })
      const p = createMockCategory({ id: 2, headerId: null, parentId: 1, name: 'P' })
      const c = createMockCategory({ id: 3, headerId: null, parentId: 2, name: 'C' })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [gp, p, c],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, getAllCategoriesFlat } = useCategories()
      await fetchCategories(true)

      const flat = getAllCategoriesFlat()
      expect(flat).toHaveLength(3)
      expect(flat.map((cat) => cat.name)).toEqual(['GP', 'P', 'C'])
    })
  })

  describe('findCategoryInTree', () => {
    it('should find a child category by ID', async () => {
      const parent = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'Parent' })
      const child = createMockCategory({ id: 2, headerId: null, parentId: 1, name: 'Child' })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [parent, child],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, findCategoryInTree } = useCategories()
      await fetchCategories(true)

      const found = findCategoryInTree(2)
      expect(found).not.toBeNull()
      expect(found!.name).toBe('Child')
    })

    it('should return null for nonexistent ID', async () => {
      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, findCategoryInTree } = useCategories()
      await fetchCategories(true)

      expect(findCategoryInTree(999)).toBeNull()
    })
  })

  describe('markCategoryAsRead', () => {
    it('should mark a child category as read', async () => {
      const parent = createMockCategory({ id: 1, headerId: 1, parentId: null, name: 'Parent' })
      const child = createMockCategory({
        id: 2,
        headerId: null,
        parentId: 1,
        name: 'Child',
        readAt: null,
      })

      const mockResponse: CategoryHeader[] = [
        {
          id: 1,
          name: 'Header',
          description: null,
          sortOrder: 0,
          createdAt: Date.now(),
          categories: [parent, child],
        },
      ]

      vi.mocked(ocs.get).mockResolvedValueOnce({ data: mockResponse } as unknown as Promise<{
        data: CategoryHeader[]
      }>)

      const { fetchCategories, markCategoryAsRead, findCategoryInTree } = useCategories()
      await fetchCategories(true)

      markCategoryAsRead(2)

      const found = findCategoryInTree(2)
      expect(found!.readAt).toBeDefined()
      expect(found!.readAt).toBeGreaterThan(0)
    })
  })
})
