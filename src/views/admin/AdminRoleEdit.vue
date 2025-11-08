<template>
  <div class="admin-role-edit">
    <div class="page-header">
      <div class="header-actions">
        <NcButton @click="goBack">
          <template #icon>
            <ArrowLeftIcon :size="20" />
          </template>
          {{ strings.back }}
        </NcButton>
      </div>
      <div>
        <h2>{{ isEditing ? strings.editRole : strings.createRole }}</h2>
        <p class="muted">{{ strings.subtitle }}</p>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="center mt-16">
      <NcLoadingIcon :size="32" />
      <span class="muted ml-8">{{ strings.loading }}</span>
    </div>

    <!-- Error state -->
    <NcEmptyContent v-else-if="error" :title="strings.errorTitle" :description="error" class="mt-16">
      <template #action>
        <NcButton @click="refresh">{{ strings.retry }}</NcButton>
      </template>
    </NcEmptyContent>

    <!-- Form -->
    <div v-else class="role-form">
      <!-- Basic Info Section -->
      <section class="form-section">
        <h3>{{ strings.basicInfo }}</h3>
        <div class="form-grid">
          <div class="form-group">
            <label for="role-name">{{ strings.name }} *</label>
            <input id="role-name" v-model="formData.name" type="text" :placeholder="strings.namePlaceholder"
              :disabled="isSystemRole" required />
            <p v-if="isSystemRole" class="help-text muted">
              {{ strings.systemRoleNameWarning }}
            </p>
          </div>

          <div class="form-group">
            <label for="role-description">{{ strings.description }}</label>
            <textarea id="role-description" v-model="formData.description" :placeholder="strings.descriptionPlaceholder"
              rows="3" />
          </div>
        </div>
      </section>

      <!-- Role Permissions Section -->
      <section class="form-section">
        <h3>{{ strings.rolePermissions }}</h3>
        <p class="muted">{{ strings.rolePermissionsDesc }}</p>

        <div class="permissions-checkboxes">
          <div class="checkbox-group">
            <NcCheckboxRadioSwitch v-model="formData.canAccessAdminTools">
              <strong>{{ strings.canAccessAdminTools }}</strong>
              <span class="checkbox-desc muted">{{ strings.canAccessAdminToolsDesc }}</span>
            </NcCheckboxRadioSwitch>
          </div>

          <div class="checkbox-group">
            <NcCheckboxRadioSwitch v-model="formData.canEditRoles">
              <strong>{{ strings.canEditRoles }}</strong>
              <span class="checkbox-desc muted">{{ strings.canEditRolesDesc }}</span>
            </NcCheckboxRadioSwitch>
          </div>

          <div class="checkbox-group">
            <NcCheckboxRadioSwitch v-model="formData.canEditCategories">
              <strong>{{ strings.canEditCategories }}</strong>
              <span class="checkbox-desc muted">{{ strings.canEditCategoriesDesc }}</span>
            </NcCheckboxRadioSwitch>
          </div>
        </div>
      </section>

      <!-- Category Permissions Section -->
      <section class="form-section">
        <h3>{{ strings.categoryPermissions }}</h3>
        <p v-if="isAdmin" class="info-message">
          <InformationIcon :size="20" />
          {{ strings.adminFullAccess }}
        </p>
        <p v-else class="muted">{{ strings.categoryPermissionsDesc }}</p>

        <div v-if="categoryHeaders.length > 0" class="permissions-table">
          <div class="table-header">
            <div class="col-category">{{ strings.category }}</div>
            <div class="col-permission">{{ strings.canView }}</div>
            <div class="col-permission">{{ strings.canModerate }}</div>
          </div>

          <template v-for="header in categoryHeaders" :key="`header-${header.id}`">
            <!-- Header row -->
            <div class="table-header-row">
              <div class="header-name">{{ header.name }}</div>
            </div>

            <!-- Category rows under this header -->
            <div v-for="category in header.categories" :key="category.id" class="table-row">
              <div class="col-category">
                <span class="category-name">{{ category.name }}</span>
                <span v-if="category.description" class="category-desc muted">
                  {{ category.description }}
                </span>
              </div>

              <div class="col-permission">
                <NcCheckboxRadioSwitch
                  v-model="permissions[category.id].canView"
                  :disabled="isAdmin"
                >
                  {{ strings.allow }}
                </NcCheckboxRadioSwitch>
              </div>

              <div class="col-permission">
                <NcCheckboxRadioSwitch
                  v-model="permissions[category.id].canModerate"
                  :disabled="isAdmin"
                >
                  {{ strings.allow }}
                </NcCheckboxRadioSwitch>
              </div>
            </div>
          </template>
        </div>
        <div v-else class="muted">{{ strings.noCategories }}</div>
      </section>

      <!-- Actions -->
      <div class="form-actions">
        <NcButton @click="goBack">{{ strings.cancel }}</NcButton>
        <NcButton type="primary" :disabled="!canSubmit || submitting" @click="submitForm">
          <template v-if="submitting" #icon>
            <NcLoadingIcon :size="20" />
          </template>
          {{ isEditing ? strings.update : strings.create }}
        </NcButton>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import InformationIcon from '@icons/Information.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { Role, CategoryHeader } from '@/types'

interface CategoryPermission {
  canView: boolean
  canModerate: boolean
}

export default defineComponent({
  name: 'AdminRoleEdit',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcEmptyContent,
    NcLoadingIcon,
    ArrowLeftIcon,
    InformationIcon,
  },
  data() {
    return {
      loading: false,
      submitting: false,
      error: null as string | null,
      categoryHeaders: [] as CategoryHeader[],
      formData: {
        name: '',
        description: '',
        canAccessAdminTools: false,
        canEditRoles: false,
        canEditCategories: false,
      },
      permissions: {} as Record<number, CategoryPermission>,

      strings: {
        back: t('forum', 'Back'),
        createRole: t('forum', 'Create Role'),
        editRole: t('forum', 'Edit Role'),
        subtitle: t('forum', 'Configure role permissions and category access'),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading role'),
        retry: t('forum', 'Retry'),
        basicInfo: t('forum', 'Basic Information'),
        name: t('forum', 'Name'),
        description: t('forum', 'Description'),
        namePlaceholder: t('forum', 'Enter role name'),
        descriptionPlaceholder: t('forum', 'Enter role description (optional)'),
        systemRoleNameWarning: t('forum', 'System role names cannot be changed'),
        rolePermissions: t('forum', 'Role Permissions'),
        rolePermissionsDesc: t('forum', 'Set global permissions for this role'),
        canAccessAdminTools: t('forum', 'Can Access Admin Tools'),
        canAccessAdminToolsDesc: t('forum', 'Allow access to the admin dashboard and tools'),
        canEditRoles: t('forum', 'Can Edit Roles'),
        canEditRolesDesc: t('forum', 'Allow creating, editing, and deleting roles'),
        canEditCategories: t('forum', 'Can Edit Categories'),
        canEditCategoriesDesc: t('forum', 'Allow creating, editing, and deleting categories'),
        categoryPermissions: t('forum', 'Category Permissions'),
        categoryPermissionsDesc: t('forum', 'Set which categories this role can access'),
        category: t('forum', 'Category'),
        canView: t('forum', 'Can View'),
        canModerate: t('forum', 'Can Moderate'),
        allow: t('forum', 'Allow'),
        noCategories: t('forum', 'No categories available'),
        adminFullAccess: t('forum', 'Admin role has full access to all categories'),
        cancel: t('forum', 'Cancel'),
        create: t('forum', 'Create'),
        update: t('forum', 'Update'),
      },
    }
  },
  computed: {
    isEditing(): boolean {
      return !!this.$route.params.id
    },
    roleId(): number | null {
      return this.$route.params.id ? parseInt(this.$route.params.id as string) : null
    },
    isSystemRole(): boolean {
      // System roles (Admin, Moderator, Member) - only name is locked
      return this.roleId !== null && this.roleId <= 3
    },
    isAdmin(): boolean {
      // Admin role (ID 1) has full access to everything
      return this.roleId === 1
    },
    canSubmit(): boolean {
      return this.formData.name.trim().length > 0
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

        // Load category headers with categories
        const headersResponse = await ocs.get<CategoryHeader[]>('/categories')
        this.categoryHeaders = headersResponse.data || []

        // Initialize permissions for all categories across all headers
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

        // If editing, load role data and permissions
        if (this.isEditing && this.roleId) {
          await this.loadRole()
        } else {
          // Default permissions for new roles - all categories viewable
          this.categoryHeaders.forEach((header) => {
            if (header.categories) {
              header.categories.forEach((category) => {
                this.permissions[category.id] = {
                  canView: true,
                  canModerate: false,
                }
              })
            }
          })
        }
      } catch (e) {
        console.error('Failed to load role', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async loadRole(): Promise<void> {
      if (!this.roleId) return

      // Load role details
      const roleResponse = await ocs.get<Role>(`/roles/${this.roleId}`)
      const role = roleResponse.data

      this.formData.name = role.name
      this.formData.description = role.description || ''
      this.formData.canAccessAdminTools = role.canAccessAdminTools || false
      this.formData.canEditRoles = role.canEditRoles || false
      this.formData.canEditCategories = role.canEditCategories || false

      // Load role permissions
      const permsResponse = await ocs.get<Array<{
        id: number
        categoryId: number
        roleId: number
        canView: boolean
        canModerate: boolean
      }>>(`/roles/${this.roleId}/permissions`)

      const perms = permsResponse.data || []

      // Apply loaded permissions
      perms.forEach((perm) => {
        if (this.permissions[perm.categoryId]) {
          this.permissions[perm.categoryId].canView = perm.canView
          this.permissions[perm.categoryId].canModerate = perm.canModerate
        }
      })

      // Admin role always has full access
      if (this.isAdmin) {
        this.categoryHeaders.forEach((header) => {
          if (header.categories) {
            header.categories.forEach((category) => {
              this.permissions[category.id] = {
                canView: true,
                canModerate: true,
              }
            })
          }
        })
      }
    },

    async submitForm(): Promise<void> {
      if (!this.canSubmit) return

      try {
        this.submitting = true

        const roleData = {
          name: this.formData.name.trim(),
          description: this.formData.description.trim() || null,
          canAccessAdminTools: this.formData.canAccessAdminTools,
          canEditRoles: this.formData.canEditRoles,
          canEditCategories: this.formData.canEditCategories,
        }

        let roleId: number

        if (this.isEditing && this.roleId !== null) {
          // Update existing role
          await ocs.put(`/roles/${this.roleId}`, roleData)
          roleId = this.roleId
        } else {
          // Create new role
          const response = await ocs.post<Role>('/roles', roleData)
          roleId = response.data.id
        }

        // Update permissions
        await this.updatePermissions(roleId)

        // Navigate back to role list
        this.$router.push('/admin/roles')
      } catch (e) {
        console.error('Failed to save role', e)
        // TODO: Show error notification
      } finally {
        this.submitting = false
      }
    },

    async updatePermissions(roleId: number): Promise<void> {
      const permissionsData = Object.entries(this.permissions).map(([categoryId, perms]) => ({
        categoryId: parseInt(categoryId),
        canView: perms.canView,
        canModerate: perms.canModerate,
      }))

      await ocs.post(`/roles/${roleId}/permissions`, {
        permissions: permissionsData,
      })
    },

    goBack(): void {
      this.$router.push('/admin/roles')
    },
  },
})
</script>

<style scoped lang="scss">
.admin-role-edit {
  max-width: 1200px;

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

    .header-actions {
      margin-bottom: 12px;
    }

    h2 {
      margin: 0 0 6px 0;
    }
  }

  .role-form {
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

      .info-message {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: var(--color-primary-light);
        border-radius: 8px;
        color: var(--color-primary-text);
        margin-bottom: 16px;
      }
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;

      label {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--color-main-text);
      }

      input,
      textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--color-border);
        border-radius: 4px;
        background: var(--color-main-background);
        color: var(--color-main-text);
        font-family: inherit;
        font-size: 1rem;

        &:focus {
          outline: none;
          border-color: var(--color-primary-element);
        }

        &:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }

        &::placeholder {
          color: var(--color-text-maxcontrast);
        }
      }

      textarea {
        resize: vertical;
        min-height: 60px;
      }

      .help-text {
        font-size: 0.85rem;
        margin-top: 4px;
      }
    }

    .permissions-checkboxes {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-top: 12px;

      .checkbox-group {
        padding: 12px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-background-hover);

        &:hover {
          background: var(--color-background-dark);
        }

        strong {
          display: block;
          font-weight: 600;
          color: var(--color-main-text);
          margin-bottom: 2px;
        }

        .checkbox-desc {
          display: block;
          font-size: 0.85rem;
          line-height: 1.4;
        }
      }
    }

    .permissions-table {
      display: flex;
      flex-direction: column;
      gap: 1px;
      background: var(--color-border);
      border-radius: 8px;
      overflow: hidden;

      .table-header,
      .table-row {
        display: grid;
        grid-template-columns: 1fr 150px 150px;
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

      .table-header-row {
        padding: 12px 16px;
        background: var(--color-background-dark);

        .header-name {
          font-weight: 600;
          font-size: 1rem;
          color: var(--color-main-text);
          text-transform: uppercase;
          letter-spacing: 0.05em;
        }
      }

      .table-row {
        &:hover {
          background: var(--color-background-hover);
        }

        .col-category {
          display: flex;
          flex-direction: column;
          gap: 4px;

          .category-name {
            font-weight: 500;
            color: var(--color-main-text);
          }

          .category-desc {
            font-size: 0.85rem;
          }
        }

        .col-permission {
          display: flex;
          align-items: center;
        }
      }
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding-top: 16px;
      border-top: 1px solid var(--color-border);
    }
  }
}
</style>
