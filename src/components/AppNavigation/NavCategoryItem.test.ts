import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock, createComponentMock } from '@/test-utils'
import { createMockCategory } from '@/test-mocks'

// Mock NcAppNavigationItem to render children in a slot
vi.mock('@nextcloud/vue/components/NcAppNavigationItem', () =>
  createComponentMock('NcAppNavigationItem', {
    template: '<div class="nav-item-mock" :data-name="name"><slot name="icon" /><slot /></div>',
    props: ['name', 'to', 'active'],
  }),
)

vi.mock('@icons/Forum.vue', () => createIconMock('ForumIcon'))

import NavCategoryItem from './NavCategoryItem.vue'

describe('NavCategoryItem', () => {
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
})
