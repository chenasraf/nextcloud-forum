import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CategoryPermissionsTable from './CategoryPermissionsTable.vue'
import type { CategoryPermission } from './CategoryPermissionsTable.vue'
import type { CategoryHeader } from '@/types'

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
  default: {
    name: 'NcCheckboxRadioSwitch',
    template:
      '<label class="nc-checkbox" :class="{ disabled }" @click="!disabled && $emit(\'update:model-value\', !modelValue)"><input type="checkbox" :checked="modelValue" :disabled="disabled" /><slot /></label>',
    props: ['modelValue', 'disabled', 'indeterminate'],
    emits: ['update:model-value'],
  },
}))

function createHeaders(): CategoryHeader[] {
  return [
    {
      id: 1,
      name: 'General',
      description: null,
      sortOrder: 0,
      createdAt: 0,
      categories: [
        {
          id: 10,
          headerId: 1,
          name: 'Announcements',
          description: 'Important announcements',
          slug: 'announcements',
          sortOrder: 0,
          threadCount: 5,
          postCount: 20,
          createdAt: 0,
          updatedAt: 0,
        },
        {
          id: 11,
          headerId: 1,
          name: 'Off-topic',
          description: null,
          slug: 'off-topic',
          sortOrder: 1,
          threadCount: 3,
          postCount: 10,
          createdAt: 0,
          updatedAt: 0,
        },
      ],
    },
    {
      id: 2,
      name: 'Support',
      description: null,
      sortOrder: 1,
      createdAt: 0,
      categories: [
        {
          id: 20,
          headerId: 2,
          name: 'Bug reports',
          description: null,
          slug: 'bug-reports',
          sortOrder: 0,
          threadCount: 8,
          postCount: 30,
          createdAt: 0,
          updatedAt: 0,
        },
      ],
    },
  ]
}

function createPermissions(): Record<number, CategoryPermission> {
  return {
    10: { canView: true, canModerate: false },
    11: { canView: false, canModerate: false },
    20: { canView: true, canModerate: true },
  }
}

describe('CategoryPermissionsTable', () => {
  describe('rendering', () => {
    it('should render the permissions table when categories exist', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      expect(wrapper.find('.permissions-table').exists()).toBe(true)
    })

    it('should show empty message when no categories', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: [],
          permissions: {},
        },
      })
      expect(wrapper.find('.permissions-table').exists()).toBe(false)
      expect(wrapper.text()).toContain('No categories available')
    })

    it('should render header names', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      const headerNames = wrapper.findAll('.header-name')
      expect(headerNames).toHaveLength(2)
      expect(headerNames[0].text()).toBe('General')
      expect(headerNames[1].text()).toBe('Support')
    })

    it('should render category names', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      const categoryNames = wrapper.findAll('.category-name')
      expect(categoryNames).toHaveLength(3)
      expect(categoryNames[0].text()).toBe('Announcements')
      expect(categoryNames[1].text()).toBe('Off-topic')
      expect(categoryNames[2].text()).toBe('Bug reports')
    })

    it('should render category descriptions when present', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      const descriptions = wrapper.findAll('.category-desc')
      expect(descriptions).toHaveLength(1)
      expect(descriptions[0].text()).toBe('Important announcements')
    })

    it('should render table column headers', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      const header = wrapper.find('.table-header')
      expect(header.text()).toContain('Category')
      expect(header.text()).toContain('Can view')
      expect(header.text()).toContain('Can moderate')
    })
  })

  describe('checkbox states', () => {
    it('should reflect individual category permissions', () => {
      const permissions = createPermissions()
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })
      const checkboxes = wrapper.findAll('input[type="checkbox"]')
      // 3 categories × 2 (view + moderate) + 2 headers × 2 (view + moderate) = 10
      expect(checkboxes).toHaveLength(10)
    })
  })

  describe('disabled states', () => {
    it('should disable view checkboxes when disableView is true', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
          disableView: true,
        },
      })
      // Header view checkboxes and category view checkboxes should be disabled
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      // 2 header view + 3 category view = 5 disabled checkboxes
      expect(disabledLabels.length).toBe(5)
    })

    it('should disable moderate checkboxes when disableModerate is true', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
          disableModerate: true,
        },
      })
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      // 2 header moderate + 3 category moderate = 5 disabled checkboxes
      expect(disabledLabels.length).toBe(5)
    })

    it('should disable all checkboxes when both disable props are true', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
          disableView: true,
          disableModerate: true,
        },
      })
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      // All 10 checkboxes disabled
      expect(disabledLabels.length).toBe(10)
    })

    it('should not disable any checkboxes by default', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      expect(disabledLabels.length).toBe(0)
    })
  })

  describe('category permission updates', () => {
    it('should update canView when a category view checkbox is toggled', async () => {
      const permissions = createPermissions()
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      // Category 11 (Off-topic) currently has canView=false
      // Find the category rows, second row's first checkbox (view)
      const rows = wrapper.findAll('.table-row')
      const offTopicRow = rows[1] // Off-topic is second category row
      const viewCheckbox = offTopicRow.findAll('.nc-checkbox')[0]

      await viewCheckbox.trigger('click')

      expect(permissions[11].canView).toBe(true)
      expect(wrapper.emitted('update:permissions')).toBeTruthy()
    })

    it('should update canModerate when a category moderate checkbox is toggled', async () => {
      const permissions = createPermissions()
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      // Category 10 (Announcements) currently has canModerate=false
      const rows = wrapper.findAll('.table-row')
      const announcementsRow = rows[0]
      const moderateCheckbox = announcementsRow.findAll('.nc-checkbox')[1]

      await moderateCheckbox.trigger('click')

      expect(permissions[10].canModerate).toBe(true)
      expect(wrapper.emitted('update:permissions')).toBeTruthy()
    })
  })

  describe('header toggle behavior', () => {
    it('should check all categories in header when header view is toggled on', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canModerate: false },
        11: { canView: false, canModerate: false },
        20: { canView: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        toggleHeaderView: (id: number) => void
      }
      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderView(1)

      // Both categories under "General" should now have canView=true
      expect(permissions[10].canView).toBe(true)
      expect(permissions[11].canView).toBe(true)
      // "Support" category should be unchanged
      expect(permissions[20].canView).toBe(false)
    })

    it('should uncheck all categories in header when header view is toggled off', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canModerate: false },
        11: { canView: true, canModerate: false },
        20: { canView: true, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        toggleHeaderView: (id: number) => void
      }
      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderView(1)

      // Both categories under "General" should now have canView=false
      expect(permissions[10].canView).toBe(false)
      expect(permissions[11].canView).toBe(false)
      // "Support" category should be unchanged
      expect(permissions[20].canView).toBe(true)
    })

    it('should check all categories in header when header moderate is toggled on', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canModerate: false },
        11: { canView: true, canModerate: false },
        20: { canView: true, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        toggleHeaderModerate: (id: number) => void
      }
      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderModerate(1)

      expect(permissions[10].canModerate).toBe(true)
      expect(permissions[11].canModerate).toBe(true)
      expect(permissions[20].canModerate).toBe(false)
    })
  })

  describe('header state computation', () => {
    it('should show indeterminate when some categories in header are checked', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canModerate: false },
        11: { canView: false, canModerate: false },
        20: { canView: true, canModerate: true },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        getHeaderViewState: (id: number) => { checked: boolean; indeterminate: boolean }
        getHeaderModerateState: (id: number) => { checked: boolean; indeterminate: boolean }
      }
      const vm = wrapper.vm as unknown as VM

      // General header: 1/2 view checked → indeterminate
      const generalView = vm.getHeaderViewState(1)
      expect(generalView.checked).toBe(false)
      expect(generalView.indeterminate).toBe(true)

      // General header: 0/2 moderate checked → not indeterminate
      const generalModerate = vm.getHeaderModerateState(1)
      expect(generalModerate.checked).toBe(false)
      expect(generalModerate.indeterminate).toBe(false)
    })

    it('should show checked when all categories in header are checked', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canModerate: true },
        11: { canView: true, canModerate: true },
        20: { canView: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        getHeaderViewState: (id: number) => { checked: boolean; indeterminate: boolean }
        getHeaderModerateState: (id: number) => { checked: boolean; indeterminate: boolean }
      }
      const vm = wrapper.vm as unknown as VM

      // General header: 2/2 view checked → checked
      const generalView = vm.getHeaderViewState(1)
      expect(generalView.checked).toBe(true)
      expect(generalView.indeterminate).toBe(false)

      // General header: 2/2 moderate checked → checked
      const generalModerate = vm.getHeaderModerateState(1)
      expect(generalModerate.checked).toBe(true)
      expect(generalModerate.indeterminate).toBe(false)
    })

    it('should show unchecked when no categories in header are checked', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canModerate: false },
        11: { canView: false, canModerate: false },
        20: { canView: true, canModerate: true },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        getHeaderViewState: (id: number) => { checked: boolean; indeterminate: boolean }
      }
      const vm = wrapper.vm as unknown as VM

      // General header: 0/2 view checked → unchecked
      const generalView = vm.getHeaderViewState(1)
      expect(generalView.checked).toBe(false)
      expect(generalView.indeterminate).toBe(false)
    })
  })

  describe('ensurePermission', () => {
    it('should create a default permission entry for unknown category IDs', () => {
      const permissions: Record<number, CategoryPermission> = {}
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        ensurePermission: (id: number) => CategoryPermission
      }
      const vm = wrapper.vm as unknown as VM

      const result = vm.ensurePermission(999)
      expect(result).toEqual({ canView: false, canModerate: false })
    })

    it('should return existing permission entry when it exists', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canModerate: true },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      type VM = InstanceType<typeof CategoryPermissionsTable> & {
        ensurePermission: (id: number) => CategoryPermission
      }
      const vm = wrapper.vm as unknown as VM

      const result = vm.ensurePermission(10)
      expect(result).toEqual({ canView: true, canModerate: true })
    })
  })
})
