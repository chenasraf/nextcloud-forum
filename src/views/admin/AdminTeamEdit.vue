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

    <div class="admin-team-edit">
      <PageHeader :title="teamDisplayName || strings.editTeam" :subtitle="strings.subtitle" />

      <!-- Loading state -->
      <div v-if="loading" class="center mt-16">
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
          <NcButton @click="refresh">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Form -->
      <div v-else class="team-form">
        <NcNoteCard type="info">
          {{ strings.teamPermissionsInfo }}
        </NcNoteCard>

        <!-- Category Permissions Section -->
        <section class="form-section">
          <h3>{{ strings.categoryPermissions }}</h3>
          <p class="muted">{{ strings.categoryPermissionsDesc }}</p>

          <CategoryPermissionsTable
            :category-headers="categoryHeaders"
            :permissions="permissions"
          />
        </section>

        <!-- Actions -->
        <div class="form-actions">
          <NcButton @click="goBack">{{ strings.cancel }}</NcButton>
          <NcButton variant="primary" :disabled="submitting" @click="submitForm">
            <template v-if="submitting" #icon>
              <NcLoadingIcon :size="20" />
            </template>
            {{ strings.update }}
          </NcButton>
        </div>
      </div>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import PageWrapper from '@/components/PageWrapper'
import PageHeader from '@/components/PageHeader'
import AppToolbar from '@/components/AppToolbar'
import CategoryPermissionsTable, {
  type CategoryPermission,
} from '@/components/CategoryPermissionsTable'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { CategoryHeader, Team } from '@/types'

export default defineComponent({
  name: 'AdminTeamEdit',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcNoteCard,
    PageHeader,
    ArrowLeftIcon,
    PageWrapper,
    AppToolbar,
    CategoryPermissionsTable,
  },
  data() {
    return {
      loading: false,
      submitting: false,
      error: null as string | null,
      categoryHeaders: [] as CategoryHeader[],
      teamDisplayName: '',
      permissions: {} as Record<number, CategoryPermission>,

      strings: {
        back: t('forum', 'Back'),
        editTeam: t('forum', 'Edit team'),
        subtitle: t('forum', 'Configure category permissions for this team'),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading team'),
        retry: t('forum', 'Retry'),
        teamPermissionsInfo: t(
          'forum',
          'Editing category permissions for this team. Team membership is managed via Nextcloud Teams.',
        ),
        categoryPermissions: t('forum', 'Category permissions'),
        categoryPermissionsDesc: t('forum', 'Set which categories this team can access'),
        cancel: t('forum', 'Cancel'),
        update: t('forum', 'Update'),
      },
    }
  },
  computed: {
    teamId(): string {
      return this.$route.params.id as string
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        // Load categories and teams in parallel
        const [headersResponse, teamsResponse] = await Promise.all([
          ocs.get<CategoryHeader[]>('/categories'),
          ocs.get<Team[]>('/teams'),
        ])

        this.categoryHeaders = headersResponse.data || []

        // Find the team display name
        const teams = teamsResponse.data || []
        const team = teams.find((t) => t.id === this.teamId)
        this.teamDisplayName = team?.displayName || this.teamId

        // Initialize permissions for all categories
        this.categoryHeaders.forEach((header) => {
          if (header.categories) {
            header.categories.forEach((category) => {
              this.permissions[category.id] = {
                canView: false,
                canModerate: false,
              }
            })
          }
        })

        // Load existing team permissions
        const permsResponse = await ocs.get<
          Array<{
            id: number
            categoryId: number
            canView: boolean
            canModerate: boolean
          }>
        >(`/teams/${this.teamId}/permissions`)

        const perms = permsResponse.data || []

        // Apply loaded permissions
        perms.forEach((perm) => {
          const categoryPerm = this.permissions[perm.categoryId]
          if (categoryPerm) {
            categoryPerm.canView = perm.canView
            categoryPerm.canModerate = perm.canModerate
          }
        })
      } catch (e) {
        console.error('Failed to load team', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async submitForm(): Promise<void> {
      try {
        this.submitting = true

        const permissionsData = Object.entries(this.permissions).map(([categoryId, perms]) => ({
          categoryId: parseInt(categoryId),
          canView: perms.canView,
          canModerate: perms.canModerate,
        }))

        await ocs.post(`/teams/${this.teamId}/permissions`, {
          permissions: permissionsData,
        })

        this.$router.push('/admin/roles')
      } catch (e) {
        console.error('Failed to save team permissions', e)
      } finally {
        this.submitting = false
      }
    },

    goBack(): void {
      this.$router.push('/admin/roles')
    },
  },
})
</script>

<style scoped lang="scss">
.admin-team-edit {
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

  .team-form {
    display: flex;
    flex-direction: column;
    gap: 32px;

    .form-section {
      h3 {
        margin: 0 0 16px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-main-text);
      }
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      padding-top: 16px;
      border-top: 1px solid var(--color-border);
    }
  }
}
</style>
