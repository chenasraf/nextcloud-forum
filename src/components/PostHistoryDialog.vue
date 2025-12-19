<template>
  <NcDialog :name="strings.title" :open="open" @update:open="handleClose" size="large">
    <div class="post-history-dialog">
      <!-- Loading state -->
      <div v-if="loading" class="loading-state">
        <NcLoadingIcon :size="32" />
        <span class="loading-text">{{ strings.loading }}</span>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="error-state">
        <span class="error-text">{{ error }}</span>
      </div>

      <!-- No history state -->
      <div v-else-if="!historyData || historyData.history.length === 0" class="empty-state">
        <HistoryIcon :size="48" class="empty-icon" />
        <span class="empty-text">{{ strings.noHistory }}</span>
      </div>

      <!-- History content -->
      <div v-else class="history-content">
        <!-- Current version -->
        <div class="history-entry current-version">
          <div class="entry-header">
            <span class="version-label current-label">{{ strings.currentVersion }}</span>
            <div class="entry-meta">
              <NcDateTime
                v-if="historyData.current.editedAt"
                :timestamp="historyData.current.editedAt * 1000"
              />
              <span v-else>
                <NcDateTime :timestamp="historyData.current.createdAt * 1000" />
              </span>
            </div>
          </div>
          <div class="entry-content" v-html="historyData.current.content"></div>
        </div>

        <!-- Historical versions -->
        <div v-for="(entry, index) in historyData.history" :key="entry.id" class="history-entry">
          <div class="entry-header">
            <span class="version-label">{{ getVersionLabel(index) }}</span>
            <div class="entry-meta">
              <span class="editor-info">
                {{ strings.editedBy }}
                <UserInfo
                  :user-id="entry.editor?.userId || entry.editedBy"
                  :display-name="entry.editor?.displayName || entry.editedBy"
                  :is-deleted="entry.editor?.isDeleted || false"
                  :avatar-size="20"
                  :inline="true"
                />
              </span>
              <NcDateTime :timestamp="entry.editedAt * 1000" />
            </div>
          </div>
          <div class="entry-content" v-html="entry.content"></div>
        </div>
      </div>
    </div>

    <template #actions>
      <NcButton @click="handleClose">
        {{ strings.close }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import HistoryIcon from '@icons/History.vue'
import UserInfo from '@/components/UserInfo.vue'
import { t, n } from '@nextcloud/l10n'
import { ocs } from '@/axios'
import type { PostHistoryResponse } from '@/types'

export default defineComponent({
  name: 'PostHistoryDialog',
  components: {
    NcDialog,
    NcButton,
    NcLoadingIcon,
    NcDateTime,
    HistoryIcon,
    UserInfo,
  },
  props: {
    open: {
      type: Boolean,
      required: true,
    },
    postId: {
      type: Number as PropType<number>,
      required: true,
    },
  },
  emits: ['update:open'],
  data() {
    return {
      loading: false,
      error: null as string | null,
      historyData: null as PostHistoryResponse | null,

      strings: {
        title: t('forum', 'Edit history'),
        loading: t('forum', 'Loading history …'),
        close: t('forum', 'Close'),
        noHistory: t('forum', 'This post has no edit history.'),
        currentVersion: t('forum', 'Current version'),
        editedBy: t('forum', 'Edited by'),
      },
    }
  },
  watch: {
    open: {
      immediate: true,
      handler(newValue) {
        if (newValue) {
          this.loadHistory()
        } else {
          this.historyData = null
          this.error = null
        }
      },
    },
  },
  methods: {
    async loadHistory() {
      try {
        this.loading = true
        this.error = null

        const response = await ocs.get<PostHistoryResponse>(`/posts/${this.postId}/history`)
        this.historyData = response.data
      } catch (e) {
        console.error('Failed to load post history:', e)
        this.error = t('forum', 'Failed to load edit history')
      } finally {
        this.loading = false
      }
    },

    handleClose() {
      this.$emit('update:open', false)
    },

    getVersionLabel(index: number): string {
      const totalVersions = this.historyData?.history.length || 0
      const versionNumber = totalVersions - index
      return t('forum', 'Version {index}', { index: versionNumber })
    },
  },
})
</script>

<style scoped lang="scss">
.post-history-dialog {
  padding: 8px 0;
  max-height: 60vh;
  overflow-y: auto;
}

.loading-state,
.error-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 16px;
  text-align: center;
}

.loading-text,
.empty-text {
  font-size: 0.95rem;
  color: var(--color-text-maxcontrast);
}

.error-text {
  font-size: 0.95rem;
  color: var(--color-error);
}

.empty-icon {
  color: var(--color-text-maxcontrast);
  opacity: 0.5;
}

.history-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.history-entry {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  overflow: hidden;

  &.current-version {
    border-color: var(--color-primary-element);
    background: var(--color-primary-element-light);
  }

  .entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 16px;
    background: var(--color-background-hover);
    border-bottom: 1px solid var(--color-border);
  }

  .version-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);

    &.current-label {
      color: var(--color-primary-element);
    }
  }

  .entry-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }

  .editor-info {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .entry-content {
    padding: 16px;
    line-height: 1.6;
    font-size: 0.95rem;
    color: var(--color-main-text);
    word-wrap: break-word;
    overflow-wrap: break-word;

    // Code blocks
    :deep(pre) {
      background: var(--color-background-dark);
      border: 1px solid var(--color-border);
      border-radius: 6px;
      padding: 16px;
      margin: 12px 0;
      overflow-x: auto;

      code {
        background: none;
        padding: 0;
        border: none;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        line-height: 1.5;
        color: var(--color-main-text);
        white-space: pre;
        display: block;
      }
    }

    // Inline code
    :deep(code) {
      background: var(--color-background-dark);
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Courier New', Courier, monospace;
      font-size: 0.9rem;
      color: var(--color-main-text);
    }

    // Blockquotes
    :deep(blockquote) {
      border-left: 4px solid var(--color-border-maxcontrast);
      margin: 12px 0;
      padding-left: 12px;
      color: var(--color-text-secondary);
    }

    // Lists
    :deep(ul) {
      margin: 12px 0;
      padding-left: 32px;
      list-style-type: disc;

      li {
        margin: 4px 0;
        line-height: 1.6;
      }
    }
  }
}
</style>
