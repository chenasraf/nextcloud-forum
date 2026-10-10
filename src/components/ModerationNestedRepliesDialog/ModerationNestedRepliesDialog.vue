<template>
  <NcDialog
    :name="title"
    :open="open"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <div class="nested-replies-dialog">
      <p>{{ message }}</p>
      <p class="muted">{{ strings.explanation }}</p>
    </div>

    <template #actions>
      <NcButton @click="$emit('update:open', false)">
        {{ strings.cancel }}
      </NcButton>
      <NcButton @click="$emit('choose', 'keep')">
        {{ strings.keep }}
      </NcButton>
      <NcButton variant="error" @click="$emit('choose', 'delete')">
        <template #icon>
          <DeleteForeverIcon :size="20" />
        </template>
        {{ strings.delete }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import DeleteForeverIcon from '@icons/DeleteForever.vue'
import { t, n } from '@nextcloud/l10n'

export type NestedRepliesMode = 'keep' | 'delete'

export default defineComponent({
  name: 'ModerationNestedRepliesDialog',
  components: {
    NcButton,
    NcDialog,
    DeleteForeverIcon,
  },
  props: {
    open: { type: Boolean, required: true },
    /** Number of replies being permanently deleted */
    postCount: { type: Number, default: 1 },
    /** Number of nested replies below them, at any depth */
    replyCount: { type: Number, required: true },
  },
  emits: ['update:open', 'choose'],
  data() {
    return {
      strings: {
        explanation: t(
          'forum',
          'Kept nested replies stay where they are, under a placeholder for the deleted reply. This cannot be undone.',
        ),
        cancel: t('forum', 'Cancel'),
        keep: t('forum', 'Keep nested replies'),
        delete: t('forum', 'Delete nested replies'),
      },
    }
  },
  computed: {
    title(): string {
      return n('forum', 'Permanently delete reply', 'Permanently delete %n replies', this.postCount)
    },
    message(): string {
      return this.postCount === 1
        ? n(
            'forum',
            'This reply has %n nested reply. What should happen to it?',
            'This reply has %n nested replies. What should happen to them?',
            this.replyCount,
          )
        : n(
            'forum',
            'The selected replies have %n nested reply. What should happen to it?',
            'The selected replies have %n nested replies. What should happen to them?',
            this.replyCount,
          )
    },
  },
})
</script>

<style scoped lang="scss">
.nested-replies-dialog {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px;
}
</style>
