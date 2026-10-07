<template>
  <PageWrapper>
    <div class="admin-moderation">
      <PageHeader :title="strings.title" :subtitle="strings.subtitle" />

      <!-- Tabs -->
      <div class="tabs-header">
        <button
          class="tab-button"
          :class="{ active: activeTab === 'threads' }"
          @click="switchTab('threads')"
        >
          {{ strings.deletedThreads }}
        </button>
        <button
          class="tab-button"
          :class="{ active: activeTab === 'replies' }"
          @click="switchTab('replies')"
        >
          {{ strings.deletedReplies }}
        </button>
      </div>

      <!-- Search + Sort -->
      <div class="controls">
        <NcTextField
          v-model="search"
          :placeholder="strings.searchPlaceholder"
          :label="strings.search"
          class="search-input"
          @update:model-value="debouncedLoad"
        />
        <NcButton @click="toggleSort">
          <template #icon>
            <SortCalendarDescendingIcon v-if="sort === 'newest'" :size="20" />
            <SortCalendarAscendingIcon v-else :size="20" />
          </template>
          {{ sort === 'newest' ? strings.newestFirst : strings.oldestFirst }}
        </NcButton>
      </div>

      <!-- List -->
      <ModerationDeletedList
        :mode="activeTab"
        :items="items"
        :total="total"
        :page="page"
        :per-page="perPage"
        :loading="loading"
        :error="error"
        :restoring="restoring"
        :deleting="deleting"
        :selected-ids="selectedIds"
        :bulk-deleting="bulkDeleting"
        @view="handleView"
        @restore="handleRestore"
        @delete="handleDelete"
        @retry="loadData"
        @update:page="goToPage"
        @update:selected-ids="selectedIds = $event"
        @bulk-delete="bulkDelete"
      />

      <!-- Thread preview dialog -->
      <ModerationThreadDialog
        :open="showThreadDialog"
        :thread-id="dialogThreadId"
        :thread-title="dialogThreadTitle"
        :restoring="restoring === dialogThreadId"
        :deleting="deleting === dialogThreadId"
        @update:open="showThreadDialog = $event"
        @restore="restoreThread"
        @delete="deleteThread"
      />

      <!-- Nested replies choice when permanently deleting replies -->
      <ModerationNestedRepliesDialog
        :open="nestedRepliesDialog.open"
        :post-count="nestedRepliesDialog.postCount"
        :reply-count="nestedRepliesDialog.replyCount"
        @update:open="!$event && resolveNestedReplies(null)"
        @choose="resolveNestedReplies"
      />
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PageWrapper from '@/components/PageWrapper'
import PageHeader from '@/components/PageHeader'
import ModerationDeletedList from '@/components/ModerationDeletedList'
import ModerationThreadDialog from '@/components/ModerationThreadDialog'
import ModerationNestedRepliesDialog, {
  type NestedRepliesMode,
} from '@/components/ModerationNestedRepliesDialog'
import SortCalendarDescendingIcon from '@icons/SortCalendarDescending.vue'
import SortCalendarAscendingIcon from '@icons/SortCalendarAscending.vue'
import { ocs } from '@/axios'
import { t, n } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'

let debounceTimer: ReturnType<typeof setTimeout> | null = null

export default defineComponent({
  name: 'AdminModerationView',
  components: {
    NcButton,
    NcTextField,
    PageWrapper,
    PageHeader,
    ModerationDeletedList,
    ModerationThreadDialog,
    ModerationNestedRepliesDialog,
    SortCalendarDescendingIcon,
    SortCalendarAscendingIcon,
  },
  data() {
    return {
      activeTab: 'threads' as 'threads' | 'replies',
      loading: false,
      error: null as string | null,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      items: [] as any[],
      total: 0,
      page: 1,
      perPage: 20,
      search: '',
      sort: 'newest' as 'newest' | 'oldest',
      restoring: null as number | null,
      deleting: null as number | null,
      selectedIds: [] as number[],
      bulkDeleting: false,

      // Dialogs
      showThreadDialog: false,
      dialogThreadId: null as number | null,
      dialogThreadTitle: '',
      nestedRepliesDialog: {
        open: false,
        postCount: 1,
        replyCount: 0,
        resolve: null as ((mode: NestedRepliesMode | null) => void) | null,
      },
      strings: {
        title: t('forum', 'Moderation'),
        subtitle: t('forum', 'Review and restore deleted content'),
        deletedThreads: t('forum', 'Deleted threads'),
        deletedReplies: t('forum', 'Deleted replies'),
        search: t('forum', 'Search'),
        searchPlaceholder: t('forum', 'Search deleted content …'),
        newestFirst: t('forum', 'Newest first'),
        oldestFirst: t('forum', 'Oldest first'),
        confirmDeleteThread: t(
          'forum',
          'Permanently delete this thread and all its replies? This cannot be undone.',
        ),
        confirmDeleteReply: t('forum', 'Permanently delete this reply? This cannot be undone.'),
        deleteThreadError: t('forum', 'Failed to permanently delete thread'),
        deleteReplyError: t('forum', 'Failed to permanently delete reply'),
      },
    }
  },
  created() {
    this.loadData()
  },
  methods: {
    switchTab(tab: 'threads' | 'replies'): void {
      if (this.activeTab === tab) return
      this.activeTab = tab
      this.page = 1
      this.search = ''
      this.loadData()
    },

    toggleSort(): void {
      this.sort = this.sort === 'newest' ? 'oldest' : 'newest'
      this.page = 1
      this.loadData()
    },

    debouncedLoad(): void {
      if (debounceTimer) clearTimeout(debounceTimer)
      debounceTimer = setTimeout(() => {
        this.page = 1
        this.loadData()
      }, 300)
    },

    goToPage(p: number): void {
      this.page = p
      this.loadData()
    },

    async loadData(): Promise<void> {
      try {
        this.loading = true
        this.error = null
        // Selection refers to the currently displayed page; reset it on any reload
        this.selectedIds = []

        const endpoint =
          this.activeTab === 'threads' ? '/moderation/threads' : '/moderation/replies'
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const response = await ocs.get<{ items: any[]; total: number }>(endpoint, {
          params: {
            limit: this.perPage,
            offset: (this.page - 1) * this.perPage,
            search: this.search,
            sort: this.sort,
          },
        })

        this.items = response.data.items
        this.total = response.data.total
      } catch (e) {
        console.error('Failed to load moderation data', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    handleView(item: any): void {
      this.dialogThreadId = item.id
      this.dialogThreadTitle = item.title || ''
      this.showThreadDialog = true
    },

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    handleRestore(item: any): void {
      if (this.activeTab === 'threads') {
        this.restoreThreadById(item.id)
      } else {
        this.restoreReplyById(item.id)
      }
    },

    async restoreThread(): Promise<void> {
      if (!this.dialogThreadId) return
      await this.restoreThreadById(this.dialogThreadId)
      this.showThreadDialog = false
      this.dialogThreadId = null
    },

    async restoreThreadById(id: number): Promise<void> {
      try {
        this.restoring = id
        await ocs.post(`/moderation/threads/${id}/restore`)
        await this.loadData()
      } catch (e: any) {
        console.error('Failed to restore thread', e)
        showError(t('forum', 'Failed to restore thread'))
      } finally {
        this.restoring = null
      }
    },

    async restoreReplyById(id: number): Promise<void> {
      try {
        this.restoring = id
        await ocs.post(`/moderation/replies/${id}/restore`)
        await this.loadData()
      } catch (e: any) {
        console.error('Failed to restore reply', e)
        showError(t('forum', 'Failed to restore reply'))
      } finally {
        this.restoring = null
      }
    },

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    handleDelete(item: any): void {
      if (this.activeTab === 'threads') {
        this.deleteThreadById(item.id)
      } else {
        this.deleteReply(item)
      }
    },

    /**
     * Ask what to do with the nested replies below replies being permanently
     * deleted. Resolves to null when cancelled.
     */
    askNestedReplies(postCount: number, replyCount: number): Promise<NestedRepliesMode | null> {
      this.resolveNestedReplies(null)
      return new Promise((resolve) => {
        this.nestedRepliesDialog = { open: true, postCount, replyCount, resolve }
      })
    },

    resolveNestedReplies(mode: NestedRepliesMode | null): void {
      const resolve = this.nestedRepliesDialog.resolve
      this.nestedRepliesDialog.open = false
      this.nestedRepliesDialog.resolve = null
      resolve?.(mode)
    },

    /**
     * Confirm permanently deleting replies, asking about their nested replies
     * when they have any. Resolves to the nested replies mode, or null when
     * cancelled.
     */
    async confirmDeleteReplies(
      postCount: number,
      replyCount: number,
      confirmMessage: string,
    ): Promise<NestedRepliesMode | null> {
      if (replyCount > 0) {
        return this.askNestedReplies(postCount, replyCount)
      }
      return window.confirm(confirmMessage) ? 'keep' : null
    },

    async deleteThread(): Promise<void> {
      if (!this.dialogThreadId) return
      const id = this.dialogThreadId
      const deleted = await this.deleteThreadById(id)
      if (deleted) {
        this.showThreadDialog = false
        this.dialogThreadId = null
      }
    },

    async deleteThreadById(id: number): Promise<boolean> {
      if (!window.confirm(this.strings.confirmDeleteThread)) return false
      try {
        this.deleting = id
        await ocs.delete(`/moderation/threads/${id}`)
        await this.loadData()
        return true
      } catch (e: any) {
        console.error('Failed to permanently delete thread', e)
        showError(this.strings.deleteThreadError)
        return false
      } finally {
        this.deleting = null
      }
    },

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    async deleteReply(item: any): Promise<boolean> {
      const id: number = item.id
      const replies = await this.confirmDeleteReplies(
        1,
        item.replyCount ?? 0,
        this.strings.confirmDeleteReply,
      )
      if (!replies) return false
      try {
        this.deleting = id
        await ocs.delete(`/moderation/replies/${id}`, { params: { replies } })
        await this.loadData()
        return true
      } catch (e: any) {
        console.error('Failed to permanently delete reply', e)
        showError(this.strings.deleteReplyError)
        return false
      } finally {
        this.deleting = null
      }
    },

    async bulkDelete(): Promise<void> {
      const ids = [...this.selectedIds]
      if (ids.length === 0) return

      let body: { ids: number[]; replies?: NestedRepliesMode }
      if (this.activeTab === 'threads') {
        const confirmMsg = n(
          'forum',
          'Permanently delete %n selected thread and all its replies? This cannot be undone.',
          'Permanently delete %n selected threads and all their replies? This cannot be undone.',
          ids.length,
        )
        if (!window.confirm(confirmMsg)) return
        body = { ids }
      } else {
        const replyCount = this.items
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          .filter((item: any) => ids.includes(item.id))
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          .reduce((sum: number, item: any) => sum + (item.replyCount ?? 0), 0)
        const replies = await this.confirmDeleteReplies(
          ids.length,
          replyCount,
          n(
            'forum',
            'Permanently delete %n selected reply? This cannot be undone.',
            'Permanently delete %n selected replies? This cannot be undone.',
            ids.length,
          ),
        )
        if (!replies) return
        body = { ids, replies }
      }

      try {
        this.bulkDeleting = true
        const endpoint =
          this.activeTab === 'threads'
            ? '/moderation/threads/bulk-delete'
            : '/moderation/replies/bulk-delete'
        const response = await ocs.post<{
          deleted: number[]
          failed: { id: number; error: string }[]
        }>(endpoint, body)

        const deletedCount = response.data?.deleted?.length ?? 0
        const failed = response.data?.failed ?? []

        if (failed.length > 0) {
          showError(
            n(
              'forum',
              'Deleted %n item; the rest could not be removed.',
              'Deleted %n items; the rest could not be removed.',
              deletedCount,
            ),
          )
        } else {
          showSuccess(
            n(
              'forum',
              'Permanently deleted %n item.',
              'Permanently deleted %n items.',
              deletedCount,
            ),
          )
        }

        await this.loadData()
      } catch (e: any) {
        console.error('Failed to bulk delete', e)
        showError(t('forum', 'Failed to permanently delete the selected items'))
      } finally {
        this.bulkDeleting = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-moderation {
  .tabs-header {
    display: flex;
    border-bottom: 1px solid var(--color-border);
    margin-bottom: 16px;
  }

  .tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: var(--color-text-maxcontrast);
    transition: all 0.2s;
    border-radius: 0;

    &:hover {
      color: var(--color-text-light);
      background: var(--color-background-hover);
    }

    &.active {
      color: var(--color-text-light);
      border-bottom-color: var(--color-text-light);
    }
  }

  .controls {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    margin-bottom: 16px;

    .search-input {
      flex: 1;
    }
  }
}
</style>
