<template>
  <PageWrapper>
    <template #toolbar>
      <AppToolbar>
        <template #left>
          <NcButton @click="goBack">
            <template #icon>
              <ArrowLeftIcon :size="20" />
            </template>
            {{ strings.back }}
          </NcButton>
        </template>
      </AppToolbar>
    </template>

    <div class="create-thread-view">
      <!-- Page Header -->
      <PageHeader
        :title="strings.title"
        :subtitle="category ? strings.subtitle(category.name) : ''"
        class="mt-16"
      />

      <!-- Loading state -->
      <div class="center mt-16" v-if="loading && !category">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loading }}</span>
      </div>

      <!-- Error state -->
      <NcEmptyContent
        v-else-if="error"
        :title="strings.errorTitle"
        :description="error"
        class="mt-16"
      >
        <template #action>
          <NcButton @click="goBack">
            <template #icon>
              <ArrowLeftIcon :size="20" />
            </template>
            {{ strings.back }}
          </NcButton>
        </template>
      </NcEmptyContent>

      <!-- Create Thread Form -->
      <div v-else class="mt-16">
        <ThreadCreateForm
          ref="createForm"
          :draft-status="draftStatus"
          @submit="handleCreateThread"
          @cancel="goBack"
          @update:title="handleTitleChange"
          @update:content="handleContentChange"
        />
      </div>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AppToolbar from '@/components/AppToolbar'
import PageWrapper from '@/components/PageWrapper'
import PageHeader from '@/components/PageHeader'
import ThreadCreateForm, { type DraftStatus } from '@/components/ThreadCreateForm'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import type { Category, Thread, Draft } from '@/types'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'

const DRAFT_DEBOUNCE_DELAY = 1500 // 1.5 seconds

export default defineComponent({
  name: 'CreateThreadView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    AppToolbar,
    PageWrapper,
    PageHeader,
    ThreadCreateForm,
    ArrowLeftIcon,
  },
  data() {
    return {
      loading: false,
      category: null as Category | null,
      error: null as string | null,
      // Draft state
      draftTitle: '',
      draftContent: '',
      draftStatus: null as DraftStatus,
      draftDebounceTimer: null as ReturnType<typeof setTimeout> | null,

      strings: {
        back: t('forum', 'Back'),
        title: t('forum', 'Create New Thread'),
        subtitle: (categoryName: string) => t('forum', 'In {category}', { category: categoryName }),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading category'),
        creating: t('forum', 'Creating thread …'),
        success: t('forum', 'Thread created'),
        errorCreating: t('forum', 'Failed to create thread'),
      },
    }
  },
  computed: {
    categoryId(): number | null {
      return this.$route.params.categoryId
        ? parseInt(this.$route.params.categoryId as string)
        : null
    },
    categorySlug(): string | null {
      return (this.$route.params.categorySlug as string) || null
    },
  },
  created() {
    this.fetchCategory()
  },
  beforeUnmount() {
    // Clear any pending draft save
    if (this.draftDebounceTimer) {
      clearTimeout(this.draftDebounceTimer)
    }
  },
  methods: {
    async fetchCategory() {
      if (!this.categoryId && !this.categorySlug) {
        this.error = t('forum', 'No category specified')
        return
      }

      try {
        this.loading = true
        this.error = null

        let resp
        if (this.categorySlug) {
          resp = await ocs.get<Category>(`/categories/slug/${this.categorySlug}`)
        } else if (this.categoryId) {
          resp = await ocs.get<Category>(`/categories/${this.categoryId}`)
        }
        this.category = resp!.data

        // After loading category, fetch any existing draft
        await this.fetchDraft()
      } catch (e) {
        console.error('Failed to fetch category', e)
        this.error = t('forum', 'Category not found')
      } finally {
        this.loading = false
      }
    },

    async fetchDraft() {
      if (!this.category) return

      try {
        const response = await ocs.get<{ draft: Draft | null }>(
          `/drafts/thread/${this.category.id}`,
        )
        const draft = response.data.draft

        if (draft) {
          // Load draft into form
          const form = this.$refs.createForm as any
          if (form) {
            form.setTitle(draft.title || '')
            form.setContent(draft.content || '')
          }
          // Update local state for tracking
          this.draftTitle = draft.title || ''
          this.draftContent = draft.content || ''
          // Mark as saved since we just loaded it
          this.draftStatus = 'saved'
        }
      } catch (e) {
        console.error('Failed to fetch draft', e)
        // Silently fail - not critical
      }
    },

    async saveDraft() {
      if (!this.category) return

      // Do not save if content is empty
      if (!this.draftContent.trim()) {
        return
      }

      try {
        this.draftStatus = 'saving'
        await ocs.put(`/drafts/thread/${this.category.id}`, {
          title: this.draftTitle || null,
          content: this.draftContent,
        })
        this.draftStatus = 'saved'
      } catch (e) {
        console.error('Failed to save draft', e)
        // On error, mark as dirty since it was not saved
        this.draftStatus = 'dirty'
      }
    },

    scheduleDraftSave() {
      // Clear any existing timer
      if (this.draftDebounceTimer) {
        clearTimeout(this.draftDebounceTimer)
      }

      // Only save if there's content
      if (!this.draftContent.trim()) {
        this.draftStatus = null
        return
      }

      // Mark as dirty immediately when user makes changes
      this.draftStatus = 'dirty'

      this.draftDebounceTimer = setTimeout(() => {
        this.saveDraft()
      }, DRAFT_DEBOUNCE_DELAY)
    },

    handleTitleChange(title: string) {
      this.draftTitle = title
      this.scheduleDraftSave()
    },

    handleContentChange(content: string) {
      this.draftContent = content
      this.scheduleDraftSave()
    },

    async handleCreateThread(data: { title: string; content: string }) {
      if (!this.category) {
        showError(this.strings.errorCreating)
        return
      }

      const form = this.$refs.createForm as any
      form?.setSubmitting(true)

      // Cancel any pending draft save
      if (this.draftDebounceTimer) {
        clearTimeout(this.draftDebounceTimer)
        this.draftDebounceTimer = null
      }

      try {
        // Create the thread with initial post in a single request
        const threadResp = await ocs.post<Thread>('/threads', {
          categoryId: this.category.id,
          title: data.title,
          content: data.content,
        })

        const newThread = threadResp.data

        showSuccess(this.strings.success)

        // Navigate to the new thread
        this.$router.push(`/t/${newThread.slug}`)
      } catch (e) {
        console.error('Failed to create thread', e)
        showError(this.strings.errorCreating)
        form?.setSubmitting(false)
      }
    },

    goBack(): void {
      // Navigate back to the category
      if (this.category) {
        this.$router.push(`/c/${this.category.slug || this.category.id}`)
      } else {
        this.$router.push('/')
      }
    },
  },
})
</script>

<style scoped lang="scss">
.create-thread-view {
  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .ml-8 {
    margin-left: 8px;
  }

  .center {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .page-header {
    padding: 20px;
    background: var(--color-background-hover);
    border-radius: 8px;
    border: 1px solid var(--color-border);
  }

  .page-title {
    margin: 0 0 4px 0;
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--color-main-text);
  }

  .page-subtitle {
    margin: 0;
    font-size: 1rem;
    color: var(--color-text-lighter);
  }
}
</style>
