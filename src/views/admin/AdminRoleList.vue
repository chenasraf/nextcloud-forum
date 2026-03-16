<template>
  <PageWrapper :full-width="true">
    <template #toolbar>
      <AppToolbar>
        <template #right>
          <NcButton @click="createRole" variant="primary">
            <template #icon>
              <PlusIcon :size="20" />
            </template>
            {{ strings.createRole }}
          </NcButton>
        </template>
      </AppToolbar>
    </template>

    <div class="admin-role-list">
      <PageHeader :title="strings.title" :subtitle="strings.subtitle" />

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

      <!-- Role list -->
      <AdminTable
        v-else-if="roles.length > 0"
        :columns="tableColumns"
        :rows="roles"
        row-key="id"
        :has-actions="true"
        :actions-label="strings.actions"
      >
        <template #cell-id="{ row }">
          <span class="role-id">{{ row.id }}</span>
        </template>

        <template #cell-name="{ row }">
          <RoleBadge :role="row" />
        </template>

        <template #cell-description="{ row }">
          <span v-if="row.description" class="role-description">{{ row.description }}</span>
          <span v-else class="muted">{{ strings.noDescription }}</span>
        </template>

        <template #cell-created="{ row }">
          <NcDateTime :timestamp="row.createdAt * 1000" />
        </template>

        <template #actions="{ row }">
          <NcActions variant="secondary">
            <NcActionButton @click="editRole(row.id)">
              <template #icon>
                <PencilIcon :size="20" />
              </template>
              {{ strings.edit }}
            </NcActionButton>
            <NcActionButton :disabled="row.isSystemRole" @click="confirmDelete(row)">
              <template #icon>
                <DeleteIcon :size="20" />
              </template>
              {{ strings.delete }}
            </NcActionButton>
          </NcActions>
        </template>
      </AdminTable>

      <!-- Empty state -->
      <NcEmptyContent
        v-else
        :title="strings.emptyTitle"
        :description="strings.emptyDesc"
        class="mt-16"
      >
        <template #action>
          <NcButton @click="createRole">
            <template #icon>
              <PlusIcon :size="20" />
            </template>
            {{ strings.createRole }}
          </NcButton>
        </template>
      </NcEmptyContent>

      <!-- Teams Section -->
      <PageHeader :title="strings.teamsTitle" :subtitle="strings.teamsSubtitle" class="mt-32" />

      <!-- Teams Loading state -->
      <div v-if="teamsLoading" class="center mt-16">
        <NcLoadingIcon :size="32" />
        <span class="muted ml-8">{{ strings.loadingTeams }}</span>
      </div>

      <!-- Teams Error state -->
      <NcEmptyContent
        v-else-if="teamsError"
        :title="strings.teamsErrorTitle"
        :description="teamsError"
        class="mt-16"
      >
        <template #action>
          <NcButton @click="refreshTeams">{{ strings.retry }}</NcButton>
        </template>
      </NcEmptyContent>

      <!-- Teams list -->
      <AdminTable
        v-else-if="teams.length > 0"
        :columns="teamTableColumns"
        :rows="teams"
        row-key="id"
        :has-actions="true"
        :actions-label="strings.actions"
      >
        <template #cell-id="{ row }">
          <span class="team-id" :title="row.id">{{ row.id }}</span>
        </template>

        <template #cell-displayName="{ row }">
          <span class="team-name">
            <AccountGroupIcon :size="20" class="team-icon" />
            {{ row.displayName }}
          </span>
        </template>

        <template #cell-memberCount="{ row }">
          <span>{{ row.memberCount }}</span>
        </template>

        <template #cell-ownerDisplayName="{ row }">
          <span>{{ row.ownerDisplayName }}</span>
        </template>

        <template #actions="{ row }">
          <NcActions variant="secondary">
            <NcActionButton @click="editTeam(row.id)">
              <template #icon>
                <PencilIcon :size="20" />
              </template>
              {{ strings.edit }}
            </NcActionButton>
          </NcActions>
        </template>
      </AdminTable>

      <!-- Teams empty state -->
      <NcEmptyContent
        v-else
        :title="strings.teamsEmptyTitle"
        :description="strings.teamsEmptyDesc"
        class="mt-16"
      />
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import AdminTable, { type TableColumn } from '@/components/AdminTable'
import RoleBadge from '@/components/RoleBadge'
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import AccountGroupIcon from '@icons/AccountGroup.vue'
import PageWrapper from '@/components/PageWrapper'
import PageHeader from '@/components/PageHeader'
import AppToolbar from '@/components/AppToolbar'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { Role, Team } from '@/types'

export default defineComponent({
  name: 'AdminRoleList',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcDateTime,
    NcActions,
    NcActionButton,
    AdminTable,
    RoleBadge,
    PlusIcon,
    PencilIcon,
    DeleteIcon,
    AccountGroupIcon,
    PageWrapper,
    PageHeader,
    AppToolbar,
  },
  data() {
    return {
      loading: false,
      roles: [] as Role[],
      error: null as string | null,
      teamsLoading: false,
      teams: [] as Team[],
      teamsError: null as string | null,

      strings: {
        title: t('forum', 'Role management'),
        subtitle: t('forum', 'Create and manage forum roles and permissions'),
        loading: t('forum', 'Loading roles …'),
        errorTitle: t('forum', 'Error loading roles'),
        retry: t('forum', 'Retry'),
        emptyTitle: t('forum', 'No roles found'),
        emptyDesc: t('forum', 'Create your first role to get started'),
        createRole: t('forum', 'Create role'),
        id: t('forum', 'ID'),
        name: t('forum', 'Name'),
        displayName: t('forum', 'Name'),
        owner: t('forum', 'Owner'),
        description: t('forum', 'Description'),
        created: t('forum', 'Created'),
        actions: t('forum', 'Actions'),
        edit: t('forum', 'Edit'),
        delete: t('forum', 'Delete'),
        noDescription: t('forum', 'No description'),
        confirmDeleteMessage: (name: string) =>
          t(
            'forum',
            'Are you sure you want to delete the role "{name}"? This action cannot be undone.',
            { name },
          ),
        systemRoleWarning: t('forum', 'System roles cannot be deleted'),
        teamsTitle: t('forum', 'Team permissions'),
        teamsSubtitle: t('forum', 'Manage category permissions for Nextcloud Teams'),
        loadingTeams: t('forum', 'Loading teams …'),
        teamsErrorTitle: t('forum', 'Error loading teams'),
        teamsEmptyTitle: t('forum', 'No teams found'),
        memberCount: t('forum', 'Members'),
        teamsEmptyDesc: t('forum', 'No Nextcloud Teams are available'),
      },
    }
  },
  computed: {
    tableColumns(): TableColumn[] {
      return [
        { key: 'id', label: this.strings.id, minWidth: '50px', maxWidth: '100px' },
        { key: 'name', label: this.strings.name, minWidth: '120px' },
        { key: 'description', label: this.strings.description, minWidth: '250px' },
        { key: 'created', label: this.strings.created, minWidth: '120px' },
      ]
    },
    teamTableColumns(): TableColumn[] {
      return [
        { key: 'id', label: this.strings.id, minWidth: '150px' },
        { key: 'displayName', label: this.strings.displayName, minWidth: '200px' },
        {
          key: 'memberCount',
          label: this.strings.memberCount,
          minWidth: '100px',
          maxWidth: '150px',
        },
        {
          key: 'ownerDisplayName',
          label: this.strings.owner,
          minWidth: '100px',
          maxWidth: '200px',
        },
      ]
    },
  },
  created() {
    this.refresh()
    this.refreshTeams()
  },
  methods: {
    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        const response = await ocs.get<Role[]>('/roles')
        this.roles = response.data || []
      } catch (e) {
        console.error('Failed to load roles', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async refreshTeams(): Promise<void> {
      try {
        this.teamsLoading = true
        this.teamsError = null

        const response = await ocs.get<Team[]>('/teams')
        this.teams = response.data || []
      } catch (e) {
        console.error('Failed to load teams', e)
        this.teamsError = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.teamsLoading = false
      }
    },

    createRole(): void {
      this.$router.push('/admin/roles/create')
    },

    editRole(roleId: number): void {
      this.$router.push(`/admin/roles/${roleId}/edit`)
    },

    editTeam(teamId: string): void {
      this.$router.push(`/admin/teams/${teamId}/edit`)
    },

    confirmDelete(role: Role): void {
      if (role.isSystemRole) {
        alert(this.strings.systemRoleWarning)
        return
      }

      if (confirm(this.strings.confirmDeleteMessage(role.name))) {
        this.deleteRole(role.id)
      }
    },

    async deleteRole(roleId: number): Promise<void> {
      try {
        await ocs.delete(`/roles/${roleId}`)
        await this.refresh()
      } catch (e) {
        console.error('Failed to delete role', e)
        // TODO: Show error notification
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-role-list {
  .muted {
    color: var(--color-text-maxcontrast);
    opacity: 0.7;
  }

  .mt-16 {
    margin-top: 16px;
  }

  .mt-32 {
    margin-top: 32px;
  }

  .ml-8 {
    margin-left: 8px;
  }

  .center {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  // Custom cell content styling
  :deep(.role-id) {
    font-weight: 600;
    font-family: monospace;
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);
  }

  :deep(.role-description) {
    color: var(--color-text-lighter);
    font-size: 0.9rem;
  }

  :deep(.team-id) {
    font-weight: 600;
    font-family: monospace;
    font-size: 0.9rem;
    color: var(--color-text-maxcontrast);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
  }

  :deep(.team-name) {
    display: flex;
    align-items: center;
    gap: 8px;

    .team-icon {
      color: var(--color-primary-element);
      flex-shrink: 0;
    }
  }
}
</style>
