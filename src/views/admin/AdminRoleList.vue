<template>
  <PageWrapper>
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
      <div v-else-if="roles.length > 0" class="roles-content">
        <div class="roles-table">
          <div class="table-header">
            <div class="col-id">{{ strings.id }}</div>
            <div class="col-name">{{ strings.name }}</div>
            <div class="col-description">{{ strings.description }}</div>
            <div class="col-created">{{ strings.created }}</div>
            <div class="col-actions">{{ strings.actions }}</div>
          </div>

          <div v-for="role in roles" :key="role.id" class="table-row">
            <div class="col-id">
              <span class="role-id">{{ role.id }}</span>
            </div>

            <div class="col-name">
              <span class="role-name" :class="getRoleClass(role.id)">{{ role.name }}</span>
            </div>

            <div class="col-description">
              <span v-if="role.description" class="role-description">{{ role.description }}</span>
              <span v-else class="muted">{{ strings.noDescription }}</span>
            </div>

            <div class="col-created">
              <NcDateTime :timestamp="role.createdAt * 1000" />
            </div>

            <div class="col-actions">
              <NcActions>
                <NcActionButton @click="editRole(role.id)">
                  <template #icon>
                    <PencilIcon :size="20" />
                  </template>
                  {{ strings.edit }}
                </NcActionButton>
                <NcActionButton :disabled="isSystemRole(role.id)" @click="confirmDelete(role)">
                  <template #icon>
                    <DeleteIcon :size="20" />
                  </template>
                  {{ strings.delete }}
                </NcActionButton>
              </NcActions>
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
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import DeleteIcon from '@icons/Delete.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import AppToolbar from '@/components/AppToolbar.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { Role } from '@/types'

export default defineComponent({
  name: 'AdminRoleList',
  components: {
    NcButton,
    NcEmptyContent,
    NcLoadingIcon,
    NcDateTime,
    NcActions,
    NcActionButton,
    PlusIcon,
    PencilIcon,
    DeleteIcon,
    PageWrapper,
    PageHeader,
    AppToolbar,
  },
  data() {
    return {
      loading: false,
      roles: [] as Role[],
      error: null as string | null,

      strings: {
        title: t('forum', 'Role Management'),
        subtitle: t('forum', 'Create and manage forum roles and permissions'),
        loading: t('forum', 'Loading roles…'),
        errorTitle: t('forum', 'Error loading roles'),
        retry: t('forum', 'Retry'),
        emptyTitle: t('forum', 'No roles found'),
        emptyDesc: t('forum', 'Create your first role to get started'),
        createRole: t('forum', 'Create Role'),
        id: t('forum', 'ID'),
        name: t('forum', 'Name'),
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
      },
    }
  },
  created() {
    this.refresh()
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

    isSystemRole(roleId: number): boolean {
      // System roles (Admin, Moderator, Member) cannot be deleted
      return roleId <= 3
    },

    getRoleClass(roleId: number): string {
      const roleClasses: Record<number, string> = {
        1: 'role-admin',
        2: 'role-moderator',
        3: 'role-member',
      }
      return roleClasses[roleId] || ''
    },

    createRole(): void {
      this.$router.push('/admin/roles/create')
    },

    editRole(roleId: number): void {
      this.$router.push(`/admin/roles/${roleId}/edit`)
    },

    confirmDelete(role: Role): void {
      if (this.isSystemRole(role.id)) {
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

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
    }

    h2 {
      margin: 0 0 6px 0;
    }
  }

  .roles-content {
    .roles-table {
      display: flex;
      flex-direction: column;
      gap: 1px;
      background: var(--color-border);
      border-radius: 8px;
      overflow: hidden;

      .table-header,
      .table-row {
        display: grid;
        grid-template-columns: 60px 200px 1fr 150px 80px;
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

        .col-id {
          .role-id {
            font-weight: 600;
            font-family: monospace;
            font-size: 0.9rem;
            color: var(--color-text-maxcontrast);
          }
        }

        .col-name {
          .role-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--color-main-text);

            &.role-admin {
              color: var(--color-error);
            }

            &.role-moderator {
              color: var(--color-warning);
            }

            &.role-member {
              color: var(--color-primary);
            }
          }
        }

        .col-description {
          .role-description {
            color: var(--color-text-lighter);
            font-size: 0.9rem;
          }
        }

        .col-actions {
          display: flex;
          justify-content: flex-end;
        }
      }
    }
  }
}
</style>
