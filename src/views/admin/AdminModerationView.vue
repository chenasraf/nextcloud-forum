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
        @view="handleView"
        @restore="handleRestore"
        @retry="loadData"
        @update:page="goToPage"
      />

      <!-- Thread preview dialog -->
      <ModerationThreadDialog
        :open="showThreadDialog"
        :thread-id="dialogThreadId"
        :thread-title="dialogThreadTitle"
        :restoring="restoring === dialogThreadId"
        @update:open="showThreadDialog = $event"
        @restore="restoreThread"
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
import SortCalendarDescendingIcon from '@icons/SortCalendarDescending.vue'
import SortCalendarAscendingIcon from '@icons/SortCalendarAscending.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'

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

      // Dialogs
      showThreadDialog: false,
      dialogThreadId: null as number | null,
      dialogThreadTitle: '',
      strings: {
        title: t('forum', 'Moderation'),
        subtitle: t('forum', 'Review and restore deleted content'),
        deletedThreads: t('forum', 'Deleted threads'),
        deletedReplies: t('forum', 'Deleted replies'),
        search: t('forum', 'Search'),
        searchPlaceholder: t('forum', 'Search deleted content …'),
        newestFirst: t('forum', 'Newest first'),
        oldestFirst: t('forum', 'Oldest first'),
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
