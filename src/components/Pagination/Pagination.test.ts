import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from './Pagination.vue'

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => ({
  t: (app: string, text: string, vars?: Record<string, unknown>) => {
    if (vars) {
      return Object.entries(vars).reduce(
        (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
        text,
      )
    }
    return text
  },
}))

// Mock @nextcloud/vue/components/NcButton
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button :disabled="disabled" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
    props: ['variant', 'disabled', 'ariaLabel', 'title'],
  },
}))

// Mock icon components
vi.mock('@icons/PageFirst.vue', () => ({
  default: { name: 'PageFirstIcon', template: '<span>«</span>', props: ['size'] },
}))
vi.mock('@icons/PageLast.vue', () => ({
  default: { name: 'PageLastIcon', template: '<span>»</span>', props: ['size'] },
}))
vi.mock('@icons/ChevronLeft.vue', () => ({
  default: { name: 'ChevronLeftIcon', template: '<span>‹</span>', props: ['size'] },
}))
vi.mock('@icons/ChevronRight.vue', () => ({
  default: { name: 'ChevronRightIcon', template: '<span>›</span>', props: ['size'] },
}))

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
      // Access the computed property via vm
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
      // Should show: 1, 2, 3 (first 3) + ellipsis + 18, 19, 20 (last 3)
      expect(pageItems).toEqual([1, 2, 3, 'ellipsis', 18, 19, 20])
    })

    it('should add ellipsis for pages > 10 when on last page', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 20, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      // Should show: 1, 2, 3 (first 3) + ellipsis + 18, 19, 20 (last 3)
      expect(pageItems).toEqual([1, 2, 3, 'ellipsis', 18, 19, 20])
    })

    it('should show pages around current page in the middle', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 10, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      // Should show: 1, 2, 3 + ellipsis + 8, 9, 10, 11, 12 + ellipsis + 18, 19, 20
      expect(pageItems).toEqual([1, 2, 3, 'ellipsis', 8, 9, 10, 11, 12, 'ellipsis', 18, 19, 20])
    })

    it('should handle edge case where current page is near the start', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 4, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      // Current=4, so around: 2,3,4,5,6, combined with first 3 (1,2,3): 1,2,3,4,5,6
      expect(pageItems).toContain(1)
      expect(pageItems).toContain(4)
      expect(pageItems).toContain(6)
    })

    it('should handle edge case where current page is near the end', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 17, maxPages: 20 },
      })
      const pageItems = (wrapper.vm as unknown as { pageItems: (number | 'ellipsis')[] }).pageItems
      // Current=17, so around: 15,16,17,18,19, combined with last 3 (18,19,20): 15,16,17,18,19,20
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

      // Find all buttons and click the one for page 3
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
      // First two buttons are first page and previous page
      expect(buttons[0].attributes('disabled')).toBeDefined()
      expect(buttons[1].attributes('disabled')).toBeDefined()
    })

    it('should disable next/last buttons on last page', () => {
      const wrapper = mount(Pagination, {
        props: { currentPage: 10, maxPages: 10 },
      })

      const buttons = wrapper.findAll('button')
      // Last two buttons are next page and last page
      const lastIdx = buttons.length - 1
      expect(buttons[lastIdx].attributes('disabled')).toBeDefined()
      expect(buttons[lastIdx - 1].attributes('disabled')).toBeDefined()
    })
  })
})
