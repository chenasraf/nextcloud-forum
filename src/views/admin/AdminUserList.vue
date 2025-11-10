<template>
  <div class="admin-user-list">
    <div class="page-header">
      <h2>{{ strings.title }}</h2>
      <p class="muted">{{ strings.subtitle }}</p>
    </div>

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
    <div v-else-if="users.length > 0" class="users-content">
      <div class="users-table">
        <div class="table-header">
          <div class="col-user">{{ strings.user }}</div>
          <div class="col-posts">{{ strings.posts }}</div>
          <div class="col-roles">{{ strings.roles }}</div>
          <div class="col-joined">{{ strings.joined }}</div>
          <div class="col-status">{{ strings.status }}</div>
        </div>

        <div
          v-for="user in users"
          :key="user.userId"
          class="table-row"
          :class="{ 'is-deleted': user.isDeleted }"
        >
          <div class="col-user">
            <NcAvatar :user="user.userId" :size="40" />
            <div class="user-info">
              <div class="user-name">{{ user.displayName }}</div>
              <div class="user-id muted">@{{ user.userId }}</div>
            </div>
          </div>

          <div class="col-posts">
            <div class="post-stats">
              <div class="stat-item">
                <span class="stat-value">{{ user.threadCount }}</span>
                <span class="stat-label muted">threads</span>
              </div>
              <div class="stat-divider">/</div>
              <div class="stat-item">
                <span class="stat-value">{{ user.postCount }}</span>
                <span class="stat-label muted">posts</span>
              </div>
            </div>
          </div>

          <div class="col-roles">
            <div v-if="editingUserId === user.userId" class="roles-editor">
              <NcSelect
                v-model="editingRoles"
                :options="roleOptions"
                :placeholder="strings.selectRoles"
                :multiple="true"
                label="name"
                track-by="id"
                input-label="name"
                class="roles-select"
              />
              <div class="edit-actions">
                <NcButton @click="cancelEdit" :aria-label="strings.cancel" :title="strings.cancel">
                  <template #icon>
                    <CloseIcon :size="20" />
                  </template>
                </NcButton>
                <NcButton
                  variant="primary"
                  @click="saveRoles(user.userId)"
                  :aria-label="strings.save"
                  :title="strings.save"
                >
                  <template #icon>
                    <CheckIcon :size="20" />
                  </template>
                </NcButton>
              </div>
            </div>
            <div v-else class="roles-display">
              <div class="roles-list">
                <span
                  v-for="roleId in user.roles"
                  :key="roleId"
                  class="role-badge"
                  :class="getRoleBadgeClass(roleId)"
                >
                  {{ getRoleName(roleId) }}
                </span>
                <span v-if="user.roles.length === 0" class="muted">{{ strings.noRoles }}</span>
              </div>
              <NcButton
                @click="startEdit(user.userId, user.roles)"
                :aria-label="strings.edit"
                :title="strings.edit"
              >
                <template #icon>
                  <PencilIcon :size="20" />
                </template>
              </NcButton>
            </div>
          </div>

          <div class="col-joined">
            <NcDateTime :timestamp="user.createdAt * 1000" />
          </div>

          <div class="col-status">
            <span v-if="user.isDeleted" class="status-badge status-deleted">
              {{ strings.deleted }}
            </span>
            <span v-else class="status-badge status-active">
              {{ strings.active }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <NcEmptyContent
      v-else
      :title="strings.emptyTitle"
      :description="strings.emptyDesc"
      class="mt-16"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PencilIcon from '@icons/Pencil.vue'
import CheckIcon from '@icons/Check.vue'
import CloseIcon from '@icons/Close.vue'
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
  roles: number[]
}

interface RoleOption {
  id: number
  name: string
}

export default defineComponent({
  name: 'AdminUserList',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcAvatar,
    NcDateTime,
    NcSelect,
    PencilIcon,
    CheckIcon,
    CloseIcon,
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
        active: t('forum', 'Active'),
        deleted: t('forum', 'Deleted'),
        noRoles: t('forum', 'No roles'),
        selectRoles: t('forum', 'Select roles'),
        edit: t('forum', 'Edit roles'),
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

    getRoleName(roleId: number): string {
      const role = this.allRoles.find((r) => r.id === roleId)
      return role?.name || t('forum', 'Unknown Role')
    },

    getRoleBadgeClass(roleId: number): string {
      const roleClasses: Record<number, string> = {
        1: 'role-admin',
        2: 'role-moderator',
        3: 'role-member',
      }
      return roleClasses[roleId] || 'role-unknown'
    },

    startEdit(userId: string, currentRoles: number[]): void {
      this.editingUserId = userId
      this.originalRoles = [...currentRoles]

      // Convert role IDs to role options for NcSelectTags
      // IMPORTANT: Must use the same object references from roleOptions
      this.editingRoles = this.roleOptions.filter((option) => currentRoles.includes(option.id))
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
          user.roles = newRoleIds
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

  .page-header {
    margin-bottom: 24px;

    h2 {
      margin: 0 0 6px 0;
    }
  }

  .users-content {
    .users-table {
      display: flex;
      flex-direction: column;
      gap: 1px;
      background: var(--color-border);
      border-radius: 8px;
      overflow: hidden;

      .table-header,
      .table-row {
        display: grid;
        grid-template-columns: 2fr 100px 2fr 150px 100px;
        gap: 16px;
        padding: 16px;
        background: var(--color-main-background);
        align-items: center;
      }

      .table-header {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-text-maxcontrast);
        background: var(--color-background-hover);
      }

      .table-row {
        &:hover {
          background: var(--color-background-hover);
        }

        &.is-deleted {
          opacity: 0.6;
        }

        .col-user {
          display: flex;
          align-items: center;
          gap: 12px;

          .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;

            .user-name {
              font-weight: 500;
              color: var(--color-main-text);
            }

            .user-id {
              font-size: 0.85rem;
            }
          }
        }

        .col-posts {
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
        }

        .col-roles {
          .roles-editor {
            display: flex;
            align-items: center;
            gap: 8px;

            .roles-select {
              flex: 1;
              min-width: 200px;
            }

            .edit-actions {
              display: flex;
              gap: 4px;
            }
          }

          .roles-display {
            display: flex;
            align-items: center;
            gap: 8px;

            .roles-list {
              display: flex;
              flex-wrap: wrap;
              gap: 6px;
              flex: 1;

              .role-badge {
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
                white-space: nowrap;

                &.role-admin {
                  background: var(--color-error-light);
                  color: var(--color-error-dark);
                }

                &.role-moderator {
                  background: var(--color-warning-light);
                  color: var(--color-warning-dark);
                }

                &.role-member {
                  background: var(--color-primary-light);
                  color: var(--color-primary-dark);
                }

                &.role-unknown {
                  background: var(--color-background-dark);
                  color: var(--color-text-maxcontrast);
                }
              }
            }
          }
        }

        .col-status {
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
        }
      }
    }
  }
}
</style>
