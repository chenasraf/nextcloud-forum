import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminTable from './AdminTable.vue'

describe('AdminTable', () => {
  const defaultColumns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
  ]

  const defaultRows = [
    { id: 1, name: 'John Doe', email: 'john@example.com' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com' },
  ]

  describe('rendering', () => {
    it('should render column headers', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows },
      })
      expect(wrapper.find('.header-row').text()).toContain('Name')
      expect(wrapper.find('.header-row').text()).toContain('Email')
    })

    it('should render data rows', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows },
      })
      const dataRows = wrapper.findAll('.data-row')
      expect(dataRows).toHaveLength(2)
    })

    it('should render cell values', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows },
      })
      expect(wrapper.text()).toContain('John Doe')
      expect(wrapper.text()).toContain('john@example.com')
    })
  })

  describe('actions column', () => {
    it('should not show actions column by default', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows },
      })
      expect(wrapper.find('.col-actions').exists()).toBe(false)
    })

    it('should show actions column when hasActions is true', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows, hasActions: true },
      })
      expect(wrapper.findAll('.col-actions').length).toBeGreaterThan(0)
    })

    it('should use custom actions label', () => {
      const wrapper = mount(AdminTable, {
        props: {
          columns: defaultColumns,
          rows: defaultRows,
          hasActions: true,
          actionsLabel: 'Operations',
        },
      })
      expect(wrapper.find('.header-row').text()).toContain('Operations')
    })
  })

  describe('grid style', () => {
    it('should compute grid template columns', () => {
      const columns = [
        { key: 'name', label: 'Name', width: '200px' },
        { key: 'email', label: 'Email', minWidth: '150px' },
      ]
      const wrapper = mount(AdminTable, {
        props: { columns, rows: defaultRows },
      })
      const grid = wrapper.find('.table-grid')
      expect(grid.attributes('style')).toContain('grid-template-columns')
    })
  })

  describe('row key', () => {
    it('should use id as row key by default', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows },
      })
      expect(wrapper.findAll('.data-row')).toHaveLength(2)
    })

    it('should use custom row key', () => {
      const rows = [
        { customId: 'a', name: 'Test' },
        { customId: 'b', name: 'Test 2' },
      ]
      const wrapper = mount(AdminTable, {
        props: { columns: [{ key: 'name', label: 'Name' }], rows, rowKey: 'customId' },
      })
      expect(wrapper.findAll('.data-row')).toHaveLength(2)
    })
  })

  describe('slots', () => {
    it('should render custom cell content via slot', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows },
        slots: {
          'cell-name': '<span class="custom-cell">Custom Name</span>',
        },
      })
      expect(wrapper.findAll('.custom-cell').length).toBeGreaterThan(0)
    })

    it('should render actions slot', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows, hasActions: true },
        slots: {
          actions: '<button class="action-btn">Edit</button>',
        },
      })
      expect(wrapper.findAll('.action-btn').length).toBeGreaterThan(0)
    })
  })

  describe('row class', () => {
    it('should apply string row class', () => {
      const wrapper = mount(AdminTable, {
        props: { columns: defaultColumns, rows: defaultRows, rowClass: 'custom-row' },
      })
      expect(wrapper.findAll('.data-row.custom-row')).toHaveLength(2)
    })

    it('should apply function row class', () => {
      const wrapper = mount(AdminTable, {
        props: {
          columns: defaultColumns,
          rows: defaultRows,
          rowClass: (row: { id: number }) => (row.id === 1 ? 'first-row' : ''),
        },
      })
      expect(wrapper.find('.data-row.first-row').exists()).toBe(true)
    })
  })
})
