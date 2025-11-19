<template>
  <div class="admin-table">
    <div class="table-scroll-container">
      <div class="table-grid" :style="gridStyle">
        <!-- Header Row -->
        <div class="table-row header-row">
          <div v-for="column in columns" :key="column.key" :class="`col-${column.key}`">
            {{ column.label }}
          </div>
          <div v-if="hasActions" class="col-actions">{{ actionsLabel }}</div>
        </div>

        <!-- Data Rows -->
        <div
          v-for="row in rows"
          :key="getRowKey(row)"
          class="table-row data-row"
          :class="getRowClass(row)"
        >
          <div v-for="column in columns" :key="column.key" :class="`col-${column.key}`">
            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
              {{ row[column.key] }}
            </slot>
          </div>
          <div v-if="hasActions" class="col-actions">
            <slot name="actions" :row="row" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'

export interface TableColumn {
  key: string
  label: string
  minWidth?: string
  maxWidth?: string
  width?: string
}

export default defineComponent({
  name: 'AdminTable',
  props: {
    columns: {
      type: Array as PropType<TableColumn[]>,
      required: true,
    },
    rows: {
      type: Array as PropType<any[]>,
      required: true,
    },
    rowKey: {
      type: String,
      default: 'id',
    },
    hasActions: {
      type: Boolean,
      default: false,
    },
    actionsLabel: {
      type: String,
      default: 'Actions',
    },
    actionsWidth: {
      type: String,
      default: '98px',
    },
    rowClass: {
      type: [String, Function] as PropType<string | ((row: any) => string)>,
      default: '',
    },
  },
  computed: {
    gridStyle(): { gridTemplateColumns: string } {
      const columnWidths = this.columns.map((col) => {
        if (col.width) {
          return col.width
        }
        const minWidth = col.minWidth || '120px'
        const maxWidth = col.maxWidth || 'auto'
        return `minmax(${minWidth}, ${maxWidth})`
      })

      if (this.hasActions) {
        columnWidths.push(this.actionsWidth)
      }

      return {
        gridTemplateColumns: columnWidths.join(' '),
      }
    },
    totalColumns(): number {
      return this.columns.length + (this.hasActions ? 1 : 0)
    },
  },
  methods: {
    getRowKey(row: any): string | number {
      return row[this.rowKey]
    },
    getRowClass(row: any): string {
      if (typeof this.rowClass === 'function') {
        return this.rowClass(row)
      }
      return this.rowClass
    },
  },
})
</script>

<style scoped lang="scss">
.admin-table {
  .table-scroll-container {
    overflow-x: auto;
    background: var(--color-border);

    &::-webkit-scrollbar {
      height: 8px;
    }

    &::-webkit-scrollbar-track {
      background: var(--color-background-dark);
    }

    &::-webkit-scrollbar-thumb {
      background: var(--color-text-maxcontrast);
      border-radius: 4px;

      &:hover {
        background: var(--color-main-text);
      }
    }
  }

  .table-grid {
    display: grid;
    width: fit-content;
    min-width: 100%;

    .table-row {
      display: contents;

      >div {
        padding: 16px;
        background: var(--color-main-background);
        display: flex;
        align-items: center;
        transition: background 0.15s ease;
        border-right: 1px solid var(--color-border);
        border-bottom: 1px solid var(--color-border);

        &:last-child {
          border-right: none;
        }
      }
    }

    .header-row>div {
      font-weight: 600;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--color-text-maxcontrast);
      background: var(--color-background-hover);
      transition: none;
    }

    .data-row {
      &:hover>div {
        background: var(--color-background-hover);
      }

      &:last-child>div {
        border-bottom: none;
      }
    }

    .col-actions {
      justify-content: center;
      position: sticky;
      right: 0;
      z-index: 1;
      box-shadow: -8px 0 12px rgba(0, 0, 0, 0.08);

      @media (min-width: 1025px) {
        box-shadow: -4px 0 8px rgba(0, 0, 0, 0.05);
      }

      // Ensure background covers scrolled content
      &::before {
        content: '';
        position: absolute;
        inset: 0;
        background: inherit;
        z-index: -1;
      }
    }
  }
}
</style>
