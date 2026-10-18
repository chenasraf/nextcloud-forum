import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory } from '@/test-mocks'

// Mock NcAppNavigationItem to render children in a slot
vi.mock('@nextcloud/vue/components/NcAppNavigationItem', () =>
  createComponentMock('NcAppNavigationItem', {
    template: `<div class="nav-item-mock" :data-name="name" :data-open="String(open)" :data-collapsible="String(allowCollapse)">
      <slot name="icon" />
      <button class="toggle-mock" @click="$emit('update:open', !open)" />
      <span class="counter-mock"><slot name="counter" /></span>
      <slot />
    </div>`,
    props: ['name', 'to', 'active', 'allowCollapse', 'open'],
    emits: ['update:open'],
  }),
)

vi.mock('@icons/Forum.vue', () => createIconMock('ForumIcon'))

import NavCategoryItem from './NavCategoryItem.vue'
import { useCategories } from '@/composables/useCategories'

describe('NavCategoryItem', () => {
  beforeEach(() => {
    useCategories().unreadCounts.value = {}
  })

  it('should render the category name', () => {
    const category = createMockCategory({ id: 1, name: 'General' })
    const wrapper = mount(NavCategoryItem, {
      props: { category, active: false, activeCategoryIds: new Set<number>() },
    })
    expect(wrapper.find('[data-name="General"]').exists()).toBe(true)
  })

  it('should render direct children', () => {
    const child1 = createMockCategory({ id: 2, name: 'Child 1', parentId: 1, slug: 'child-1' })
    const child2 = createMockCategory({ id: 3, name: 'Child 2', parentId: 1, slug: 'child-2' })
    const parent = createMockCategory({
      id: 1,
      name: 'Parent',
      children: [child1, child2],
    })

    const wrapper = mount(NavCategoryItem, {
      props: { category: parent, active: false, activeCategoryIds: new Set<number>() },
    })

    const items = wrapper.findAll('.nav-item-mock')
    // Parent + 2 children = 3 items
    expect(items).toHaveLength(3)
    expect(wrapper.find('[data-name="Child 1"]').exists()).toBe(true)
    expect(wrapper.find('[data-name="Child 2"]').exists()).toBe(true)
  })

  it('should render grandchildren recursively', () => {
    const grandchild = createMockCategory({
      id: 3,
      name: 'Grandchild',
      parentId: 2,
      slug: 'grandchild',
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
      children: [child],
    })

    const wrapper = mount(NavCategoryItem, {
      props: { category: parent, active: false, activeCategoryIds: new Set<number>() },
    })

    const items = wrapper.findAll('.nav-item-mock')
    // Parent + child + grandchild = 3 items
    expect(items).toHaveLength(3)
    expect(wrapper.find('[data-name="Grandchild"]').exists()).toBe(true)
  })

  it('should not render children when there are none', () => {
    const category = createMockCategory({ id: 1, name: 'Leaf', children: [] })
    const wrapper = mount(NavCategoryItem, {
      props: { category, active: false, activeCategoryIds: new Set<number>() },
    })

    const items = wrapper.findAll('.nav-item-mock')
    expect(items).toHaveLength(1)
  })

  describe('collapsing', () => {
    const buildTree = () => {
      const grandchild = createMockCategory({
        id: 3,
        name: 'Grandchild',
        parentId: 2,
        children: [],
      })
      const child = createMockCategory({
        id: 2,
        name: 'Child',
        parentId: 1,
        children: [grandchild],
      })
      return createMockCategory({ id: 1, name: 'Parent', children: [child] })
    }

    it('should allow collapsing every category that has children', () => {
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree() },
      })

      expect(wrapper.find('[data-name="Parent"]').attributes('data-collapsible')).toBe('true')
      expect(wrapper.find('[data-name="Child"]').attributes('data-collapsible')).toBe('true')
      expect(wrapper.find('[data-name="Grandchild"]').attributes('data-collapsible')).toBe('false')
    })

    it('should be open unless marked closed', () => {
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree(), openCategories: { 2: false } },
      })

      expect(wrapper.find('[data-name="Parent"]').attributes('data-open')).toBe('true')
      expect(wrapper.find('[data-name="Child"]').attributes('data-open')).toBe('false')
    })

    it('should emit toggle from nested categories', async () => {
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree() },
      })

      await wrapper.find('[data-name="Child"] > .toggle-mock').trigger('click')

      expect(wrapper.emitted('toggle')).toEqual([[2, false]])
    })
  })

  describe('unread counts', () => {
    const buildTree = () => {
      const grandchild = createMockCategory({
        id: 3,
        name: 'Grandchild',
        parentId: 2,
        children: [],
      })
      const child = createMockCategory({
        id: 2,
        name: 'Child',
        parentId: 1,
        children: [grandchild],
      })
      return createMockCategory({ id: 1, name: 'Parent', children: [child] })
    }

    const counterOf = (wrapper: ReturnType<typeof mount>, name: string) =>
      wrapper.find(`[data-name="${name}"] > .counter-mock`).text()

    it('should show own unread count when open', () => {
      useCategories().unreadCounts.value = { 1: 1, 2: 2, 3: 4 }
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree(), showUnreadCounts: true },
      })

      expect(counterOf(wrapper, 'Parent')).toBe('1')
      expect(counterOf(wrapper, 'Child')).toBe('2')
      expect(counterOf(wrapper, 'Grandchild')).toBe('4')
    })

    it('should include descendant unread counts when collapsed', () => {
      useCategories().unreadCounts.value = { 1: 1, 2: 2, 3: 4 }
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree(), showUnreadCounts: true, openCategories: { 1: false } },
      })

      expect(counterOf(wrapper, 'Parent')).toBe('7')
    })

    it('should hide the counter at zero', () => {
      useCategories().unreadCounts.value = { 3: 4 }
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree(), showUnreadCounts: true },
      })

      expect(
        wrapper.find('[data-name="Parent"] > .counter-mock .counter-bubble-mock').exists(),
      ).toBe(false)
      expect(counterOf(wrapper, 'Grandchild')).toBe('4')
    })

    it('should hide counters when disabled', () => {
      useCategories().unreadCounts.value = { 1: 1, 2: 2, 3: 4 }
      const wrapper = mount(NavCategoryItem, {
        props: { category: buildTree(), showUnreadCounts: false },
      })

      expect(wrapper.find('.counter-bubble-mock').exists()).toBe(false)
    })
  })
})
