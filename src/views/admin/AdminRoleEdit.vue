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

    <div class="admin-role-edit">
      <PageHeader
        :title="isEditing ? strings.editRole : strings.createRole"
        :subtitle="strings.subtitle"
      />

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
      <div v-else class="role-form">
        <!-- Guest access disabled warning -->
        <NcNoteCard v-if="isGuest && !allowGuestAccess" type="error" class="guest-access-warning">
          <p>
            <strong>{{ strings.guestAccessDisabledTitle }}</strong>
          </p>
          <p>{{ strings.guestAccessDisabledMessage }}</p>
          <template #action>
            <NcButton @click="goToForumSettings" type="primary">
              {{ strings.goToForumSettings }}
            </NcButton>
          </template>
        </NcNoteCard>

        <!-- Basic Info Section -->
        <section class="form-section">
          <h3>{{ strings.basicInfo }}</h3>
          <div class="form-grid">
            <div class="form-group">
              <NcTextField
                v-model="formData.name"
                :label="strings.name"
                :placeholder="strings.namePlaceholder"
                :required="true"
              />
              <p v-if="isSystemRole" class="help-text muted">
                {{ strings.systemRoleNameWarning }}
              </p>
            </div>

            <div class="form-group">
              <NcTextArea
                v-model="formData.description"
                :label="strings.description"
                :placeholder="strings.descriptionPlaceholder"
                :rows="3"
              />
            </div>
          </div>
        </section>

        <!-- Colors Section -->
        <section class="form-section">
          <h3>{{ strings.colors }}</h3>
          <p class="muted">{{ strings.colorsDesc }}</p>

          <div class="colors-grid">
            <div class="color-group">
              <label>{{ strings.colorLight }}</label>
              <div class="color-picker-row">
                <NcColorPicker v-model="formData.colorLight" @update:value="onLightColorChange">
                  <NcButton>
                    <template #icon>
                      <div
                        class="color-preview"
                        :style="{ backgroundColor: formData.colorLight }"
                      />
                    </template>
                    {{ formData.colorLight || strings.colorLightPlaceholder }}
                  </NcButton>
                </NcColorPicker>
              </div>
            </div>

            <div class="color-group">
              <label>{{ strings.colorDark }}</label>
              <div class="color-picker-row">
                <NcColorPicker v-model="formData.colorDark" @update:value="onDarkColorChange">
                  <NcButton>
                    <template #icon>
                      <div class="color-preview" :style="{ backgroundColor: formData.colorDark }" />
                    </template>
                    {{ formData.colorDark || strings.colorDarkPlaceholder }}
                  </NcButton>
                </NcColorPicker>
                <NcButton @click="resetDarkColor">
                  {{ strings.reset }}
                </NcButton>
              </div>
            </div>
          </div>
        </section>

        <!-- Role Permissions Section -->
        <section class="form-section">
          <h3>{{ strings.rolePermissions }}</h3>
          <NcNoteCard v-if="isAdmin" type="info">
            {{ strings.adminAllRolePermissions }}
          </NcNoteCard>
          <NcNoteCard v-else-if="isGuest" type="warning">
            {{ strings.guestNoRolePermissions }}
          </NcNoteCard>
          <p v-else class="muted">{{ strings.rolePermissionsDesc }}</p>

          <div class="permissions-checkboxes">
            <div class="checkbox-group">
              <NcCheckboxRadioSwitch
                v-model="formData.canAccessAdminTools"
                :disabled="isAdmin || isGuest"
              >
                <strong>{{ strings.canAccessAdminTools }}</strong>
                <span class="checkbox-desc muted">{{ strings.canAccessAdminToolsDesc }}</span>
              </NcCheckboxRadioSwitch>
            </div>

            <div class="checkbox-group">
              <NcCheckboxRadioSwitch v-model="formData.canEditRoles" :disabled="isAdmin || isGuest">
                <strong>{{ strings.canEditRoles }}</strong>
                <span class="checkbox-desc muted">{{ strings.canEditRolesDesc }}</span>
              </NcCheckboxRadioSwitch>
            </div>

            <div class="checkbox-group">
              <NcCheckboxRadioSwitch
                v-model="formData.canEditCategories"
                :disabled="isAdmin || isGuest"
              >
                <strong>{{ strings.canEditCategories }}</strong>
                <span class="checkbox-desc muted">{{ strings.canEditCategoriesDesc }}</span>
              </NcCheckboxRadioSwitch>
            </div>
          </div>
        </section>

        <!-- Category Permissions Section -->
        <section class="form-section">
          <h3>{{ strings.categoryPermissions }}</h3>
          <NcNoteCard v-if="isAdmin" type="info">
            {{ strings.adminFullAccess }}
          </NcNoteCard>
          <NcNoteCard v-else-if="isGuest" type="info">
            <p>{{ strings.guestNoModeratePermission }}</p>
            <p class="note-subtext">
              {{ strings.guestCategoryPermissionsEditable }}
            </p>
          </NcNoteCard>
          <NcNoteCard v-else-if="isDefault" type="warning">
            {{ strings.defaultNoModeratePermission }}
          </NcNoteCard>
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
                <div class="header-permission">
                  <NcCheckboxRadioSwitch
                    :model-value="getHeaderViewState(header.id).checked"
                    :indeterminate="getHeaderViewState(header.id).indeterminate"
                    :disabled="isAdmin"
                    @update:model-value="toggleHeaderView(header.id)"
                  />
                </div>
                <div class="header-permission">
                  <NcCheckboxRadioSwitch
                    :model-value="getHeaderModerateState(header.id).checked"
                    :indeterminate="getHeaderModerateState(header.id).indeterminate"
                    :disabled="isAdmin || isGuest || isDefault"
                    @update:model-value="toggleHeaderModerate(header.id)"
                  />
                </div>
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
                    :model-value="permissions[category.id]?.canView || false"
                    :disabled="isAdmin"
                    @update:model-value="updateCategoryView(category.id, $event)"
                  >
                    {{ strings.allow }}
                  </NcCheckboxRadioSwitch>
                </div>

                <div class="col-permission">
                  <NcCheckboxRadioSwitch
                    :model-value="permissions[category.id]?.canModerate || false"
                    :disabled="isAdmin || isGuest || isDefault"
                    @update:model-value="updateCategoryModerate(category.id, $event)"
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
          <NcButton variant="primary" :disabled="!canSubmit || submitting" @click="submitForm">
            <template v-if="submitting" #icon>
              <NcLoadingIcon :size="20" />
            </template>
            {{ isEditing ? strings.update : strings.create }}
          </NcButton>
        </div>
      </div>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import PageWrapper from '@/components/PageWrapper.vue'
import PageHeader from '@/components/PageHeader.vue'
import AppToolbar from '@/components/AppToolbar.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { isAdminRole, isGuestRole, isDefaultRole } from '@/constants'
import { usePublicSettings } from '@/composables/usePublicSettings'
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
    NcColorPicker,
    NcEmptyContent,
    NcLoadingIcon,
    NcNoteCard,
    NcTextField,
    NcTextArea,
    PageHeader,
    ArrowLeftIcon,
    PageWrapper,
    AppToolbar,
  },
  setup() {
    const { allowGuestAccess, fetchPublicSettings } = usePublicSettings()

    return {
      allowGuestAccess,
      fetchPublicSettings,
    }
  },
  data() {
    return {
      loading: false,
      submitting: false,
      error: null as string | null,
      categoryHeaders: [] as CategoryHeader[],
      role: null as Role | null,
      formData: {
        name: '',
        description: '',
        colorLight: '#000000',
        colorDark: '#ffffff',
        canAccessAdminTools: false,
        canEditRoles: false,
        canEditCategories: false,
      },
      darkColorModified: false,
      permissions: {} as Record<number, CategoryPermission>,

      strings: {
        back: t('forum', 'Back'),
        createRole: t('forum', 'Create role'),
        editRole: t('forum', 'Edit role'),
        subtitle: t('forum', 'Configure role permissions and category access'),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading role'),
        retry: t('forum', 'Retry'),
        basicInfo: t('forum', 'Basic information'),
        name: t('forum', 'Name'),
        description: t('forum', 'Description'),
        namePlaceholder: t('forum', 'Enter role name'),
        descriptionPlaceholder: t('forum', 'Enter role description (optional)'),
        systemRoleNameWarning: t('forum', 'System role names cannot be changed'),
        colors: t('forum', 'Colors'),
        colorsDesc: t('forum', 'Set colors for this role badge'),
        colorLight: t('forum', 'Light mode color'),
        colorDark: t('forum', 'Dark mode color'),
        colorLightPlaceholder: '#000000',
        colorDarkPlaceholder: '#ffffff',
        reset: t('forum', 'Reset'),
        rolePermissions: t('forum', 'Role permissions'),
        rolePermissionsDesc: t('forum', 'Set global permissions for this role'),
        canAccessAdminTools: t('forum', 'Can access admin tools'),
        canAccessAdminToolsDesc: t('forum', 'Allow access to the admin dashboard and tools'),
        canEditRoles: t('forum', 'Can edit roles'),
        canEditRolesDesc: t('forum', 'Allow creating, editing and deleting roles'),
        canEditCategories: t('forum', 'Can edit categories'),
        canEditCategoriesDesc: t('forum', 'Allow creating, editing and deleting categories'),
        categoryPermissions: t('forum', 'Category permissions'),
        categoryPermissionsDesc: t('forum', 'Set which categories this role can access'),
        category: t('forum', 'Category'),
        canView: t('forum', 'Can view'),
        canModerate: t('forum', 'Can moderate'),
        allow: t('forum', 'Allow'),
        noCategories: t('forum', 'No categories available'),
        adminAllRolePermissions: t('forum', 'Admin role must have all permissions enabled'),
        adminFullAccess: t('forum', 'Admin role has full access to all categories'),
        guestNoRolePermissions: t('forum', 'Guest role cannot have admin permissions'),
        guestNoModeratePermission: t('forum', 'Guest role cannot moderate categories'),
        guestCategoryPermissionsEditable: t(
          'forum',
          'You can control which categories guests can view, post in, and reply to using the checkboxes below.',
        ),
        guestAccessDisabledTitle: t('forum', 'Guest access is currently disabled'),
        guestAccessDisabledMessage: t(
          'forum',
          'Guest users will not be able to access the forum until guest access is enabled in the forum settings.',
        ),
        goToForumSettings: t('forum', 'Go to forum settings'),
        defaultNoModeratePermission: t('forum', 'Default role cannot moderate categories'),
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
      // System roles (Admin, Moderator, User) - only name is locked
      return this.role?.isSystemRole ?? false
    },
    isAdmin(): boolean {
      return isAdminRole(this.role)
    },
    isGuest(): boolean {
      return isGuestRole(this.role)
    },
    isDefault(): boolean {
      return isDefaultRole(this.role)
    },
    canSubmit(): boolean {
      return this.formData.name.trim().length > 0
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    ensurePermission(categoryId: number): CategoryPermission {
      if (!this.permissions[categoryId]) {
        this.permissions[categoryId] = {
          canView: false,
          canModerate: false,
        }
      }
      return this.permissions[categoryId]
    },

    getHeaderViewState(headerId: number): { checked: boolean; indeterminate: boolean } {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || header.categories.length === 0) {
        return { checked: false, indeterminate: false }
      }

      const checkedCount = header.categories.filter(
        (cat) => this.permissions[cat.id]?.canView,
      ).length
      const totalCount = header.categories.length

      if (checkedCount === 0) {
        return { checked: false, indeterminate: false }
      } else if (checkedCount === totalCount) {
        return { checked: true, indeterminate: false }
      } else {
        return { checked: false, indeterminate: true }
      }
    },

    getHeaderModerateState(headerId: number): { checked: boolean; indeterminate: boolean } {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories || header.categories.length === 0) {
        return { checked: false, indeterminate: false }
      }

      const checkedCount = header.categories.filter(
        (cat) => this.permissions[cat.id]?.canModerate,
      ).length
      const totalCount = header.categories.length

      if (checkedCount === 0) {
        return { checked: false, indeterminate: false }
      } else if (checkedCount === totalCount) {
        return { checked: true, indeterminate: false }
      } else {
        return { checked: false, indeterminate: true }
      }
    },

    updateCategoryView(categoryId: number, checked: boolean): void {
      this.ensurePermission(categoryId).canView = checked
    },

    updateCategoryModerate(categoryId: number, checked: boolean): void {
      this.ensurePermission(categoryId).canModerate = checked
    },

    toggleHeaderView(headerId: number): void {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories) return

      const state = this.getHeaderViewState(headerId)
      // If all are checked, uncheck all
      // If some or none are checked, check all
      const newValue = !state.checked

      header.categories.forEach((cat) => {
        this.ensurePermission(cat.id).canView = newValue
      })
    },

    toggleHeaderModerate(headerId: number): void {
      const header = this.categoryHeaders.find((h) => h.id === headerId)
      if (!header || !header.categories) return

      const state = this.getHeaderModerateState(headerId)
      // If all are checked, uncheck all
      // If some or none are checked, check all
      const newValue = !state.checked

      header.categories.forEach((cat) => {
        this.ensurePermission(cat.id).canModerate = newValue
      })
    },
    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        // Fetch public settings to check guest access status
        await this.fetchPublicSettings()

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
      this.role = roleResponse.data

      this.formData.name = this.role.name
      this.formData.description = this.role.description || ''
      this.formData.colorLight = this.role.colorLight || '#000000'
      this.formData.colorDark = this.role.colorDark || '#ffffff'
      this.formData.canAccessAdminTools = this.role.canAccessAdminTools || false
      this.formData.canEditRoles = this.role.canEditRoles || false
      this.formData.canEditCategories = this.role.canEditCategories || false

      // Admin role always has all permissions
      if (this.isAdmin) {
        this.formData.canAccessAdminTools = true
        this.formData.canEditRoles = true
        this.formData.canEditCategories = true
      }

      // Guest role never has admin permissions
      if (this.isGuest) {
        this.formData.canAccessAdminTools = false
        this.formData.canEditRoles = false
        this.formData.canEditCategories = false
      }

      // Default role never has admin permissions (same as guest)
      if (this.isDefault) {
        this.formData.canAccessAdminTools = false
        this.formData.canEditRoles = false
        this.formData.canEditCategories = false
      }

      // If colors are different, mark dark as modified
      if (
        this.role.colorLight &&
        this.role.colorDark &&
        this.role.colorLight !== this.role.colorDark
      ) {
        this.darkColorModified = true
      }

      // Load role permissions
      const permsResponse = await ocs.get<
        Array<{
          id: number
          categoryId: number
          roleId: number
          canView: boolean
          canModerate: boolean
        }>
      >(`/roles/${this.roleId}/permissions`)

      const perms = permsResponse.data || []

      // Apply loaded permissions
      perms.forEach((perm) => {
        const categoryPerm = this.permissions[perm.categoryId]
        if (categoryPerm) {
          categoryPerm.canView = perm.canView
          categoryPerm.canModerate = perm.canModerate
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

      // Guest role never has moderate permission
      if (this.isGuest) {
        this.categoryHeaders.forEach((header) => {
          if (header.categories) {
            header.categories.forEach((category) => {
              if (this.permissions[category.id]) {
                this.permissions[category.id].canModerate = false
              }
            })
          }
        })
      }

      // Default role never has moderate permission
      if (this.isDefault) {
        this.categoryHeaders.forEach((header) => {
          if (header.categories) {
            header.categories.forEach((category) => {
              if (this.permissions[category.id]) {
                this.permissions[category.id].canModerate = false
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
          colorLight: this.formData.colorLight || null,
          colorDark: this.formData.colorDark || null,
          canAccessAdminTools: this.isAdmin
            ? true
            : this.isGuest
            ? false
            : this.formData.canAccessAdminTools,
          canEditRoles: this.isAdmin ? true : this.isGuest ? false : this.formData.canEditRoles,
          canEditCategories: this.isAdmin
            ? true
            : this.isGuest
            ? false
            : this.formData.canEditCategories,
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
        canModerate: this.isGuest || this.isDefault ? false : perms.canModerate,
      }))

      await ocs.post(`/roles/${roleId}/permissions`, {
        permissions: permissionsData,
      })
    },

    goBack(): void {
      this.$router.push('/admin/roles')
    },

    goToForumSettings(): void {
      this.$router.push('/admin/settings')
    },

    onLightColorChange(): void {
      // If dark color hasn't been manually modified, update it too
      if (!this.darkColorModified) {
        this.formData.colorDark = this.formData.colorLight
      }
    },

    onDarkColorChange(): void {
      // Mark dark color as manually modified
      this.darkColorModified = true
    },

    resetDarkColor(): void {
      // Reset dark color to match light color
      this.formData.colorDark = this.formData.colorLight
      this.darkColorModified = false
    },
  },
})
</script>

<style scoped lang="scss">
.admin-role-edit {
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

  .role-form {
    display: flex;
    flex-direction: column;
    gap: 32px;

    .guest-access-warning {
      p:first-child {
        margin-bottom: 8px;
      }

      p:last-child {
        margin: 0;
      }
    }

    .form-section {
      h3 {
        margin: 0 0 16px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-main-text);
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

      .help-text {
        font-size: 0.85rem;
        margin-top: 4px;
      }
    }

    .colors-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 24px;
      margin-top: 12px;

      .color-group {
        display: flex;
        flex-direction: column;
        gap: 16px;
        flex: 0 1 auto;

        label {
          font-weight: 600;
          color: var(--color-main-text);
          font-size: 0.95rem;
        }

        .color-picker-row {
          display: flex;
          gap: 8px;
          align-items: center;
        }

        .color-preview {
          width: 20px;
          height: 20px;
          border-radius: 4px;
          border: 1px solid var(--color-border);
        }
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
        display: grid;
        grid-template-columns: 1fr 150px 150px;
        gap: 16px;
        padding: 12px 16px;
        background: var(--color-background-dark);
        align-items: center;

        .header-name {
          font-weight: 600;
          font-size: 1rem;
          color: var(--color-main-text);
          text-transform: uppercase;
          letter-spacing: 0.05em;
        }

        .header-permission {
          display: flex;
          align-items: center;
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
      gap: 12px;
      padding-top: 16px;
      border-top: 1px solid var(--color-border);
    }
  }
}
</style>
