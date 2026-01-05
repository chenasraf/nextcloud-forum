import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createIconMock } from '@/test-utils'
import Pagination from './Pagination.vue'

vi.mock('@icons/PageFirst.vue', () => createIconMock('PageFirstIcon'))
vi.mock('@icons/PageLast.vue', () => createIconMock('PageLastIcon'))
vi.mock('@icons/ChevronLeft.vue', () => createIconMock('ChevronLeftIcon'))
vi.mock('@icons/ChevronRight.vue', () => createIconMock('ChevronRightIcon'))

describe('Pagination', () => {
  describe('visibility', () => {
    it('should not render when maxPages is 1', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 1, maxPages: 1 },
      })
      expect(wrapper.find('nav').exists()).toBe(false)
    })

    it('should render when maxPages is greater than 1', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 1, maxPages: 2 },
      })
      expect(wrapper.find('nav').exists()).toBe(true)
    })
  })

  describe('pageItems calculation', () => {
    it('should show all pages when maxPages <= 10', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 1, maxPages: 5 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toEqual([1, 2, 3, 4, 5])
    })

    it('should show all pages when maxPages is exactly 10', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 5, maxPages: 10 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    })

    it('should add ellipsis for pages > 10 when on first page', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 1, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toEqual([1, 2, 3, 'ellipsis', 18, 19, 20])
    })

    it('should add ellipsis for pages > 10 when on last page', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 20, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toEqual([1, 2, 3, 'ellipsis', 18, 19, 20])
    })

    it('should show pages around current page in the middle', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 10, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toEqual([1, 2, 3, 'ellipsis', 8, 9, 10, 11, 12, 'ellipsis', 18, 19, 20])
    })

    it('should handle edge case where current page is near the start', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 4, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toContain(1)
      expect(pageItems).toContain(4)
      expect(pageItems).toContain(6)
    })

    it('should handle edge case where current page is near the end', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 17, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      expect(pageItems).toContain(15)
      expect(pageItems).toContain(17)
      expect(pageItems).toContain(20)
    })
  })

  describe('navigation', () => {
    it('should emit update:currentPage when going to a page', async () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 5, maxPages: 10 },
      })

      const buttons = wrapper.findAll('button')
      const page3Button = buttons.find((btn) => btn.text() === '3')
      expect(page3Button).toBeDefined()

      await page3Button!.trigger('click')
      expect(wrapper.emitted('update:currentPage')).toBeTruthy()
      expect(wrapper.emitted('update:currentPage')![0]).toEqual([3])
    })

    it('should not emit when clicking current page', async () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 5, maxPages: 10 },
      })

      const buttons = wrapper.findAll('button')
      const page5Button = buttons.find((btn) => btn.text() === '5')

      await page5Button!.trigger('click')
      expect(wrapper.emitted('update:currentPage')).toBeFalsy()
    })

    it('should disable first/previous buttons on first page', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 1, maxPages: 10 },
      })

      const buttons = wrapper.findAll('button')
      expect(buttons[0]!.attributes('disabled')).toBeDefined()
      expect(buttons[1]!.attributes('disabled')).toBeDefined()
    })

    it('should disable next/last buttons on last page', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 10, maxPages: 10 },
      })

      const buttons = wrapper.findAll('button')
      const lastIdx = buttons.length - 1
      expect(buttons[lastIdx]!.attributes('disabled')).toBeDefined()
      expect(buttons[lastIdx - 1]!.attributes('disabled')).toBeDefined()
    })
  })
})
