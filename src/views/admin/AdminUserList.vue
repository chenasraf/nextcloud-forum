<template>
  <PageWrapper :full-width="true">
    <div class="admin-user-list">
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

      <!-- User list -->
      <AdminTable
        v-else-if="users.length > 0"
        :columns="tableColumns"
        :rows="users"
        row-key="userId"
        :has-actions="true"
        :actions-label="strings.actions"
        :row-class="(user) => ({ 'is-deleted': user.isDeleted })"
      >
        <template #cell-user="{ row }">
          <UserInfo :user-id="row.userId" :display-name="row.displayName" :avatar-size="40">
            <template #meta>
              <div class="user-id muted">@{{ row.userId }}</div>
            </template>
          </UserInfo>
        </template>

        <template #cell-posts="{ row }">
          <div class="post-stats">
            <div class="stat-item">
              <span class="stat-value">{{ row.threadCount }}</span>
              <span class="stat-label muted">threads</span>
            </div>
            <div class="stat-divider">/</div>
            <div class="stat-item">
              <span class="stat-value">{{ row.postCount }}</span>
              <span class="stat-label muted">posts</span>
            </div>
          </div>
        </template>

        <template #cell-roles="{ row }">
          <div class="roles-list">
            <RoleBadge v-for="role in row.roles" :key="role.id" :role="role" density="compact" />
            <span v-if="row.roles.length === 0" class="muted">{{ strings.noRoles }}</span>
          </div>
        </template>

        <template #cell-joined="{ row }">
          <NcDateTime :timestamp="row.createdAt * 1000" />
        </template>

        <template #cell-status="{ row }">
          <span v-if="row.isDeleted" class="status-badge status-deleted">
            {{ strings.deleted }}
          </span>
          <span v-else class="status-badge status-active">
            {{ strings.active }}
          </span>
        </template>

        <template #actions="{ row }">
          <NcActions variant="secondary">
            <NcActionButton
              @click="startEdit(row.userId, row.roles)"
              :aria-label="strings.editRoles"
              :title="strings.editRoles"
            >
              <template #icon>
                <PencilIcon :size="20" />
              </template>
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
      />

      <!-- Edit Roles Dialog -->
      <NcDialog v-if="editingUserId !== null" :name="strings.editRolesTitle" @close="cancelEdit">
        <div class="edit-roles-dialog">
          <NcSelect
            v-model="editingRoles"
            :options="roleOptions"
            :placeholder="strings.selectRoles"
            :multiple="true"
            label="name"
            :input-label="strings.selectRoles"
            track-by="id"
            class="roles-select"
          />
        </div>
        <template #actions>
          <NcButton @click="cancelEdit">
            {{ strings.cancel }}
          </NcButton>
          <NcButton variant="primary" @click="saveRoles(editingUserId)">
            {{ strings.save }}
          </NcButton>
        </template>
      </NcDialog>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import UserInfo from '@/components/UserInfo.vue'
import RoleBadge from '@/components/RoleBadge.vue'
import AdminTable, { type TableColumn } from '@/components/AdminTable.vue'
import PencilIcon from '@icons/Pencil.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { Role } from '@/types'

interface AdminUser {
  userId: string
  displayName: string
  postCount: number
  threadCount: number
  createdAt: number
  updatedAt: number
  deletedAt: number | null
  isDeleted: boolean
  roles: Role[]
}

interface RoleOption {
  id: number
  name: string
}

export default defineComponent({
  name: 'AdminUserList',
  components: {
    NcButton,
    NcActions,
    NcActionButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcDateTime,
    NcSelect,
    NcDialog,
    UserInfo,
    RoleBadge,
    AdminTable,
    PageWrapper,
    PageHeader,
    PencilIcon,
  },
  data() {
    return {
      loading: false,
      users: [] as AdminUser[],
      allRoles: [] as Role[],
      error: null as string | null,
      editingUserId: null as string | null,
      editingRoles: [] as RoleOption[],
      originalRoles: [] as number[],

      strings: {
        title: t('forum', 'User Management'),
        subtitle: t('forum', 'Manage forum users, roles, and permissions'),
        loading: t('forum', 'Loading users…'),
        errorTitle: t('forum', 'Error loading users'),
        retry: t('forum', 'Retry'),
        emptyTitle: t('forum', 'No users found'),
        emptyDesc: t('forum', 'There are no forum users yet'),
        user: t('forum', 'User'),
        posts: t('forum', 'Posts'),
        roles: t('forum', 'Roles'),
        joined: t('forum', 'Joined'),
        status: t('forum', 'Status'),
        actions: t('forum', 'Actions'),
        active: t('forum', 'Active'),
        deleted: t('forum', 'Deleted'),
        noRoles: t('forum', 'No roles'),
        selectRoles: t('forum', 'Select roles'),
        editRoles: t('forum', 'Edit roles'),
        editRolesTitle: t('forum', 'Edit User Roles'),
        save: t('forum', 'Save'),
        cancel: t('forum', 'Cancel'),
      },
    }
  },
  computed: {
    roleOptions(): RoleOption[] {
      return this.allRoles.map((role) => ({
        id: role.id,
        name: role.name,
      }))
    },
    tableColumns(): TableColumn[] {
      return [
        { key: 'user', label: this.strings.user, minWidth: '200px' },
        { key: 'posts', label: this.strings.posts, minWidth: '160px' },
        { key: 'roles', label: this.strings.roles, minWidth: '150px' },
        { key: 'joined', label: this.strings.joined, minWidth: '120px' },
        { key: 'status', label: this.strings.status, minWidth: '80px' },
      ]
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

        // Load users and roles in parallel
        const [usersResponse, rolesResponse] = await Promise.all([
          ocs.get<{ users: AdminUser[] }>('/admin/users'),
          ocs.get<Role[]>('/roles'),
        ])

        this.users = usersResponse.data.users || []
        this.allRoles = rolesResponse.data || []
      } catch (e) {
        console.error('Failed to load users', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    startEdit(userId: string, currentRoles: Role[]): void {
      this.editingUserId = userId
      this.originalRoles = currentRoles.map((r) => r.id)

      // Convert roles to role options for NcSelectTags
      // IMPORTANT: Must use the same object references from roleOptions
      const currentRoleIds = currentRoles.map((r) => r.id)
      this.editingRoles = this.roleOptions.filter((option) => currentRoleIds.includes(option.id))
    },

    cancelEdit(): void {
      this.editingUserId = null
      this.editingRoles = []
      this.originalRoles = []
    },

    async saveRoles(userId: string): Promise<void> {
      try {
        const newRoleIds = this.editingRoles.map((r) => r.id)
        const removedRoles = this.originalRoles.filter((id) => !newRoleIds.includes(id))
        const addedRoles = newRoleIds.filter((id) => !this.originalRoles.includes(id))

        // Get existing user role assignments for this user
        const userRolesResponse = await ocs.get<
          Array<{ id: number; roleId: number; userId: string }>
        >(`/users/${userId}/roles`)
        const existingUserRoles = userRolesResponse.data || []

        // Remove roles
        for (const roleId of removedRoles) {
          const userRole = existingUserRoles.find(
            (ur) => ur.roleId === roleId && ur.userId === userId,
          )
          if (userRole) {
            await ocs.delete(`/user-roles/${userRole.id}`)
          }
        }

        // Add new roles
        for (const roleId of addedRoles) {
          await ocs.post('/user-roles', {
            userId,
            roleId,
          })
        }

        // Update local user data
        const user = this.users.find((u) => u.userId === userId)
        if (user) {
          user.roles = this.allRoles.filter((r) => newRoleIds.includes(r.id))
        }

        this.cancelEdit()
      } catch (e) {
        console.error('Failed to save roles', e)
        // TODO: Show error notification
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-user-list {
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

  // Row-specific styling
  :deep(.is-deleted > div) {
    opacity: 0.6;
  }

  // Custom cell content styling
  .user-id {
    font-size: 0.85rem;
  }

  .post-stats {
    display: flex;
    align-items: center;
    gap: 8px;

    .stat-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2px;

      .stat-value {
        font-weight: 600;
        font-size: 1rem;
        color: var(--color-main-text);
      }

      .stat-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }
    }

    .stat-divider {
      color: var(--color-text-maxcontrast);
      font-weight: 300;
    }
  }

  .roles-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;

    &.status-active {
      background: var(--color-success-light);
      color: var(--color-success-dark);
    }

    &.status-deleted {
      background: var(--color-background-dark);
      color: var(--color-text-maxcontrast);
    }
  }

  .edit-roles-dialog {
    padding: 16px 0;

    .roles-select {
      width: 100%;
      min-width: 300px;
    }
  }
}
</style>
