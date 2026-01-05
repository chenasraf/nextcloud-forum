<template>
  <NcDialog
    :name="isEditing ? strings.editTitle : strings.createTitle"
    :open="open"
    @update:open="handleClose"
    size="small"
  >
    <div class="header-dialog-content">
      <div class="form-group">
        <NcTextField
          v-model="localName"
          :label="strings.name"
          :placeholder="strings.namePlaceholder"
          :required="true"
          @keydown.enter="handleSave"
        />
      </div>

      <div class="form-group">
        <NcTextArea
          v-model="localDescription"
          :label="strings.description"
          :placeholder="strings.descriptionPlaceholder"
          :rows="2"
        />
      </div>

      <div class="form-group">
        <NcTextField
          v-model.number="localSortOrder"
          :label="strings.sortOrder"
          :placeholder="strings.sortOrderPlaceholder"
          type="number"
        />
        <p class="help-text muted">{{ strings.sortOrderHelp }}</p>
      </div>
    </div>

    <template #actions>
      <NcButton @click="handleClose" :disabled="submitting">
        {{ strings.cancel }}
      </NcButton>
      <NcButton variant="primary" :disabled="!canSave || submitting" @click="handleSave">
        <template v-if="submitting" #icon>
          <NcLoadingIcon :size="20" />
        </template>
        {{ isEditing ? strings.update : strings.create }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { t } from '@nextcloud/l10n'
import { ocs } from '@/axios'
import type { CatHeader } from '@/types'

export default defineComponent({
  name: 'HeaderEditDialog',
  components: {
    NcDialog,
    NcButton,
    NcLoadingIcon,
    NcTextField,
    NcTextArea,
  },
  props: {
    open: {
      type: Boolean,
      required: true,
    },
    headerId: {
      type: Number as PropType<number | null>,
      default: null,
    },
    name: {
      type: String,
      default: '',
    },
    description: {
      type: String,
      default: '',
    },
    sortOrder: {
      type: Number,
      default: 0,
    },
  },
  emits: ['update:open', 'saved'],
  data() {
    return {
      localName: this.name,
      localDescription: this.description,
      localSortOrder: this.sortOrder,
      submitting: false,

      strings: {
        createTitle: t('forum', 'Create category header'),
        editTitle: t('forum', 'Edit category header'),
        name: t('forum', 'Header name'),
        namePlaceholder: t('forum', 'Enter header name'),
        description: t('forum', 'Header description'),
        descriptionPlaceholder: t('forum', 'Enter header description (optional)'),
        sortOrder: t('forum', 'Sort order'),
        sortOrderPlaceholder: '0',
        sortOrderHelp: t('forum', 'Lower numbers appear first'),
        cancel: t('forum', 'Cancel'),
        create: t('forum', 'Create'),
        update: t('forum', 'Update'),
      },
    }
  },
  computed: {
    isEditing(): boolean {
      return this.headerId !== null
    },
    canSave(): boolean {
      return this.localName.trim().length > 0
    },
  },
  watch: {
    open(newVal: boolean) {
      if (newVal) {
        // Reset local values when dialog opens
        this.localName = this.name
        this.localDescription = this.description
        this.localSortOrder = this.sortOrder
      }
    },
    name(newVal: string) {
      this.localName = newVal
    },
    description(newVal: string) {
      this.localDescription = newVal
    },
    sortOrder(newVal: number) {
      this.localSortOrder = newVal
    },
  },
  methods: {
    handleClose() {
      if (!this.submitting) {
        this.$emit('update:open', false)
      }
    },

    async handleSave(): Promise<void> {
      if (!this.canSave || this.submitting) return

      try {
        this.submitting = true

        const headerData = {
          name: this.localName.trim(),
          description: this.localDescription.trim() || null,
          sortOrder: this.localSortOrder,
        }

        let savedHeader: CatHeader

        if (this.isEditing && this.headerId !== null) {
          // Update existing header
          const response = await ocs.put<CatHeader>(`/headers/${this.headerId}`, headerData)
          savedHeader = response.data
        } else {
          // Create new header
          const response = await ocs.post<CatHeader>('/headers', headerData)
          savedHeader = response.data
        }

        this.$emit('saved', savedHeader)
        this.$emit('update:open', false)
      } catch (e) {
        console.error('Failed to save header', e)
        // TODO: Show error notification
      } finally {
        this.submitting = false
      }
    },

    reset() {
      this.localName = ''
      this.localDescription = ''
      this.localSortOrder = 0
      this.submitting = false
    },
  },
})
</script>

<style scoped lang="scss">
.header-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 8px 0;

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;

    .help-text {
      font-size: 0.85rem;
      margin-top: 4px;
      color: var(--color-text-maxcontrast);
    }
  }
}

.muted {
  color: var(--color-text-maxcontrast);
  opacity: 0.7;
}
</style>
