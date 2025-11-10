<template>
  <div class="create-thread-view">
    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <NcButton @click="goBack">
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
          {{ strings.back }}
        </NcButton>
      </div>
    </div>

    <!-- Page Header -->
    <div class="page-header mt-16">
      <h2 class="page-title">{{ strings.title }}</h2>
      <p v-if="category" class="page-subtitle">{{ strings.subtitle(category.name) }}</p>
    </div>

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
      <ThreadCreateForm ref="createForm" @submit="handleCreateThread" @cancel="goBack" />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ThreadCreateForm from '@/components/ThreadCreateForm.vue'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import type { Category, Thread } from '@/types'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default defineComponent({
  name: 'CreateThreadView',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    ThreadCreateForm,
    ArrowLeftIcon,
  },
  data() {
    return {
      loading: false,
      category: null as Category | null,
      error: null as string | null,

      strings: {
        back: t('forum', 'Back'),
        title: t('forum', 'Create New Thread'),
        subtitle: (categoryName: string) => t('forum', 'in {category}', { category: categoryName }),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading category'),
        creating: t('forum', 'Creating thread…'),
        success: t('forum', 'Thread created successfully'),
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
      } catch (e) {
        console.error('Failed to fetch category', e)
        this.error = t('forum', 'Category not found')
      } finally {
        this.loading = false
      }
    },

    async handleCreateThread(data: { title: string; content: string }) {
      if (!this.category) {
        showError(this.strings.errorCreating)
        return
      }

      const form = this.$refs.createForm as any
      form?.setSubmitting(true)

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
  max-width: 800px;
  margin: 0 auto;

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

  .toolbar {
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;

    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }
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
