<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="large"
    close-on-click-outside
    @update:open="$emit('update:open', $event)"
  >
    <!-- Loading -->
    <div v-if="loading" class="center mt-16 mb-16">
      <NcLoadingIcon :size="32" />
    </div>

    <!-- Reply -->
    <div v-else-if="reply" class="reply-dialog">
      <div v-if="reply.threadTitle" class="reply-dialog__context muted">
        {{ strings.inThread }}: <strong>{{ reply.threadTitle }}</strong>
      </div>
      <PostCard :post="reply" />
    </div>

    <template #actions>
      <NcButton variant="primary" :disabled="restoring" @click="$emit('restore')">
        <template #icon>
          <NcLoadingIcon v-if="restoring" :size="20" />
          <DeleteRestoreIcon v-else :size="20" />
        </template>
        {{ strings.restoreReply }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PostCard from '@/components/PostCard'
import DeleteRestoreIcon from '@icons/DeleteRestore.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'ModerationReplyDialog',
  components: {
    NcButton,
    NcDialog,
    NcLoadingIcon,
    PostCard,
    DeleteRestoreIcon,
  },
  props: {
    open: { type: Boolean, required: true },
    replyId: { type: Number, default: null },
    restoring: { type: Boolean, default: false },
  },
  emits: ['update:open', 'restore'],
  data() {
    return {
      loading: false,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      reply: null as any | null,
      strings: {
        title: t('forum', 'Deleted reply'),
        inThread: t('forum', 'In thread'),
        restoreReply: t('forum', 'Restore reply'),
      },
    }
  },
  watch: {
    open(val: boolean) {
      if (val && this.replyId) {
        this.loadReply()
      } else if (!val) {
        this.reply = null
      }
    },
  },
  methods: {
    async loadReply(): Promise<void> {
      if (!this.replyId) return
      try {
        this.loading = true
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const response = await ocs.get<any>(`/moderation/replies/${this.replyId}`)
        this.reply = response.data
      } catch (e) {
        console.error('Failed to load reply', e)
      } finally {
        this.loading = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.reply-dialog {
  padding: 8px;

  &__context {
    margin-bottom: 12px;
    font-size: 0.9rem;
  }
}
</style>
