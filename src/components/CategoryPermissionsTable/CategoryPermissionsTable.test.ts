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

type VM = InstanceType<typeof CategoryPermissionsTable> & {
  toggleHeaderView: (id: number) => void
  toggleHeaderPost: (id: number) => void
  toggleHeaderReply: (id: number) => void
  toggleHeaderModerate: (id: number) => void
  getHeaderViewState: (id: number) => { checked: boolean; indeterminate: boolean }
  getHeaderPostState: (id: number) => { checked: boolean; indeterminate: boolean }
  getHeaderReplyState: (id: number) => { checked: boolean; indeterminate: boolean }
  getHeaderModerateState: (id: number) => { checked: boolean; indeterminate: boolean }
  ensurePermission: (id: number) => CategoryPermission
}

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
    10: { canView: true, canPost: false, canReply: false, canModerate: false },
    11: { canView: false, canPost: false, canReply: false, canModerate: false },
    20: { canView: true, canPost: true, canReply: true, canModerate: true },
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
      expect(headerNames[0]!.text()).toBe('General')
      expect(headerNames[1]!.text()).toBe('Support')
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
      expect(categoryNames[0]!.text()).toBe('Announcements')
      expect(categoryNames[1]!.text()).toBe('Off-topic')
      expect(categoryNames[2]!.text()).toBe('Bug reports')
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
      expect(descriptions[0]!.text()).toBe('Important announcements')
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
      expect(header.text()).toContain('Can post')
      expect(header.text()).toContain('Can reply')
      expect(header.text()).toContain('Can moderate')
    })

    it('should render the info box with permission descriptions', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })
      const noteCard = wrapper.find('.nc-note-card')
      expect(noteCard.exists()).toBe(true)
      expect(noteCard.attributes('data-type')).toBe('info')
      const items = noteCard.findAll('li')
      expect(items).toHaveLength(4)
      expect(items[0]!.html()).toContain('View:')
      expect(items[1]!.html()).toContain('Post:')
      expect(items[2]!.html()).toContain('Reply:')
      expect(items[3]!.html()).toContain('Moderate:')
    })

    it('should render the info box even when no categories exist', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: [],
          permissions: {},
        },
      })
      const noteCard = wrapper.find('.nc-note-card')
      expect(noteCard.exists()).toBe(true)
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
      // 3 categories × 4 perms + 2 headers × 4 perms = 20
      expect(checkboxes).toHaveLength(20)
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
      // 2 header view + 3 category view = 5
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
      // 2 header moderate + 3 category moderate = 5
      expect(disabledLabels.length).toBe(5)
    })

    it('should disable all checkboxes when all disable props are true', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
          disableView: true,
          disablePost: true,
          disableReply: true,
          disableModerate: true,
        },
      })
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      // All 20 checkboxes disabled
      expect(disabledLabels.length).toBe(20)
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
      const offTopicRow = rows[1]! // Off-topic is second category row
      const viewCheckbox = offTopicRow.findAll('.nc-checkbox')[0]!

      await viewCheckbox.trigger('click')

      expect(permissions[11]!.canView).toBe(true)
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
      const announcementsRow = rows[0]!
      const moderateCheckbox = announcementsRow.findAll('.nc-checkbox')[3]!

      await moderateCheckbox.trigger('click')

      expect(permissions[10]!.canModerate).toBe(true)
      expect(wrapper.emitted('update:permissions')).toBeTruthy()
    })

    it('should update canPost when a category post checkbox is toggled', async () => {
      const permissions = createPermissions()
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      // Category 10 (Announcements) currently has canPost=false
      const rows = wrapper.findAll('.table-row')
      const announcementsRow = rows[0]!
      const postCheckbox = announcementsRow.findAll('.nc-checkbox')[1]!

      await postCheckbox.trigger('click')

      expect(permissions[10]!.canPost).toBe(true)
      expect(wrapper.emitted('update:permissions')).toBeTruthy()
    })

    it('should update canReply when a category reply checkbox is toggled', async () => {
      const permissions = createPermissions()
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      // Category 10 (Announcements) currently has canReply=false
      const rows = wrapper.findAll('.table-row')
      const announcementsRow = rows[0]!
      const replyCheckbox = announcementsRow.findAll('.nc-checkbox')[2]!

      await replyCheckbox.trigger('click')

      expect(permissions[10]!.canReply).toBe(true)
      expect(wrapper.emitted('update:permissions')).toBeTruthy()
    })

    it('should not emit when clicking a disabled checkbox', async () => {
      const permissions = createPermissions()
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
          disableView: true,
        },
      })

      const rows = wrapper.findAll('.table-row')
      const viewCheckbox = rows[0]!.findAll('.nc-checkbox')[0]!

      await viewCheckbox.trigger('click')

      expect(wrapper.emitted('update:permissions')).toBeFalsy()
    })
  })

  describe('header toggle behavior', () => {
    it('should check all categories in header when header view is toggled on', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canPost: false, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderView(1)

      // Both categories under "General" should now have canView=true
      expect(permissions[10]!.canView).toBe(true)
      expect(permissions[11]!.canView).toBe(true)
      // "Support" category should be unchanged
      expect(permissions[20]!.canView).toBe(false)
    })

    it('should uncheck all categories in header when header view is toggled off', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canPost: false, canReply: false, canModerate: false },
        11: { canView: true, canPost: false, canReply: false, canModerate: false },
        20: { canView: true, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderView(1)

      // Both categories under "General" should now have canView=false
      expect(permissions[10]!.canView).toBe(false)
      expect(permissions[11]!.canView).toBe(false)
      // "Support" category should be unchanged
      expect(permissions[20]!.canView).toBe(true)
    })

    it('should check all categories in header when header post is toggled on', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canPost: false, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderPost(1)

      expect(permissions[10]!.canPost).toBe(true)
      expect(permissions[11]!.canPost).toBe(true)
      expect(permissions[20]!.canPost).toBe(false)
    })

    it('should check all categories in header when header reply is toggled on', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canPost: false, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderReply(1)

      expect(permissions[10]!.canReply).toBe(true)
      expect(permissions[11]!.canReply).toBe(true)
      expect(permissions[20]!.canReply).toBe(false)
    })

    it('should check all categories in header when header moderate is toggled on', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canPost: false, canReply: false, canModerate: false },
        11: { canView: true, canPost: false, canReply: false, canModerate: false },
        20: { canView: true, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderModerate(1)

      expect(permissions[10]!.canModerate).toBe(true)
      expect(permissions[11]!.canModerate).toBe(true)
      expect(permissions[20]!.canModerate).toBe(false)
    })

    it('should emit update:permissions when header is toggled', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canPost: false, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderPost(1)

      expect(wrapper.emitted('update:permissions')).toBeTruthy()
    })

    it('should not modify permissions when header has no categories', () => {
      const headers: CategoryHeader[] = [
        {
          id: 1,
          name: 'Empty',
          description: null,
          sortOrder: 0,
          createdAt: 0,
          categories: [],
        },
      ]
      const permissions: Record<number, CategoryPermission> = {}
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: headers,
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.toggleHeaderView(1)

      expect(Object.keys(permissions)).toHaveLength(0)
    })
  })

  describe('header state computation', () => {
    it('should show indeterminate when some categories in header are checked', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canPost: false, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: true, canPost: true, canReply: true, canModerate: true },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

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
        10: { canView: true, canPost: true, canReply: true, canModerate: true },
        11: { canView: true, canPost: true, canReply: true, canModerate: true },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

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
        10: { canView: false, canPost: false, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: true, canPost: true, canReply: true, canModerate: true },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM

      // General header: 0/2 view checked → unchecked
      const generalView = vm.getHeaderViewState(1)
      expect(generalView.checked).toBe(false)
      expect(generalView.indeterminate).toBe(false)
    })

    it('should show indeterminate for post when some categories have canPost', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canPost: true, canReply: false, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      const state = vm.getHeaderPostState(1)
      expect(state.checked).toBe(false)
      expect(state.indeterminate).toBe(true)
    })

    it('should show indeterminate for reply when some categories have canReply', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: false, canPost: false, canReply: true, canModerate: false },
        11: { canView: false, canPost: false, canReply: false, canModerate: false },
        20: { canView: false, canPost: false, canReply: false, canModerate: false },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      const state = vm.getHeaderReplyState(1)
      expect(state.checked).toBe(false)
      expect(state.indeterminate).toBe(true)
    })

    it('should return unchecked for a non-existent header ID', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
        },
      })

      const vm = wrapper.vm as unknown as VM
      const state = vm.getHeaderViewState(999)
      expect(state.checked).toBe(false)
      expect(state.indeterminate).toBe(false)
    })

    it('should handle missing permission entries gracefully', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: {},
        },
      })

      const vm = wrapper.vm as unknown as VM
      // No permissions set, so header should be unchecked
      const state = vm.getHeaderViewState(1)
      expect(state.checked).toBe(false)
      expect(state.indeterminate).toBe(false)
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

      const vm = wrapper.vm as unknown as VM

      const result = vm.ensurePermission(999)
      expect(result).toEqual({
        canView: false,
        canPost: false,
        canReply: false,
        canModerate: false,
      })
    })

    it('should return existing permission entry when it exists', () => {
      const permissions: Record<number, CategoryPermission> = {
        10: { canView: true, canPost: true, canReply: true, canModerate: true },
      }
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM

      const result = vm.ensurePermission(10)
      expect(result).toEqual({ canView: true, canPost: true, canReply: true, canModerate: true })
    })

    it('should persist created permission entry in the permissions object', () => {
      const permissions: Record<number, CategoryPermission> = {}
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions,
        },
      })

      const vm = wrapper.vm as unknown as VM
      vm.ensurePermission(999)

      expect(permissions[999]).toEqual({
        canView: false,
        canPost: false,
        canReply: false,
        canModerate: false,
      })
    })
  })

  describe('disabled states for post and reply', () => {
    it('should disable post checkboxes when disablePost is true', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
          disablePost: true,
        },
      })
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      // 2 header post + 3 category post = 5
      expect(disabledLabels.length).toBe(5)
    })

    it('should disable reply checkboxes when disableReply is true', () => {
      const wrapper = mount(CategoryPermissionsTable, {
        props: {
          categoryHeaders: createHeaders(),
          permissions: createPermissions(),
          disableReply: true,
        },
      })
      const disabledLabels = wrapper.findAll('.nc-checkbox.disabled')
      // 2 header reply + 3 category reply = 5
      expect(disabledLabels.length).toBe(5)
    })
  })
})
