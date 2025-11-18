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

    <div class="admin-category-edit">
      <PageHeader
        :title="isEditing ? strings.editCategory : strings.createCategory"
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
      <div v-else class="category-form">
        <section class="form-section">
          <h3>{{ strings.basicInfo }}</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>{{ strings.categoryHeader }} *</label>
              <div class="header-select-row">
                <NcSelect
                  v-model="selectedHeader"
                  :options="headerOptions"
                  :placeholder="strings.selectHeader"
                  label="label"
                  track-by="id"
                  class="header-select"
                />
                <NcButton @click="createNewHeader">
                  <template #icon>
                    <PlusIcon :size="20" />
                  </template>
                  {{ strings.newHeader }}
                </NcButton>
                <NcButton v-if="selectedHeader" @click="editHeader">
                  <template #icon>
                    <PencilIcon :size="20" />
                  </template>
                  {{ strings.editHeader }}
                </NcButton>
              </div>
            </div>

            <div class="form-group">
              <NcTextField
                v-model="formData.name"
                :label="strings.name"
                :placeholder="strings.namePlaceholder"
                :required="true"
              />
            </div>

            <div class="form-group">
              <NcTextField
                v-model="formData.slug"
                :label="strings.slug"
                :placeholder="strings.slugPlaceholder"
                :required="true"
              />
              <p class="help-text muted">{{ strings.slugHelp }}</p>
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

        <!-- Permissions Section -->
        <section class="form-section">
          <h3>{{ strings.permissions }}</h3>
          <p class="muted">{{ strings.permissionsDescription }}</p>

          <div class="form-grid">
            <div class="form-group">
              <label>{{ strings.viewRoles }}</label>
              <NcSelect
                v-model="selectedViewRoles"
                :options="roleOptions"
                :placeholder="strings.selectRoles"
                label="label"
                track-by="id"
                :multiple="true"
                :taggable="false"
                :close-on-select="false"
              />
              <p class="help-text muted">{{ strings.viewRolesHelp }}</p>
            </div>

            <div class="form-group">
              <label>{{ strings.moderateRoles }}</label>
              <NcSelect
                v-model="selectedModerateRoles"
                :options="roleOptions"
                :placeholder="strings.selectRoles"
                label="label"
                track-by="id"
                :multiple="true"
                :taggable="false"
                :close-on-select="false"
              />
              <p class="help-text muted">{{ strings.moderateRolesHelp }}</p>
            </div>
          </div>
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

      <!-- Header Edit/Create Dialog -->
      <NcDialog
        v-if="headerDialog.show"
        :name="headerDialog.isEditing ? strings.editHeaderTitle : strings.createHeaderTitle"
        @close="headerDialog.show = false"
      >
        <div class="header-dialog-content">
          <div class="form-group">
            <NcTextField
              v-model="headerDialog.name"
              :label="strings.headerName"
              :placeholder="strings.headerNamePlaceholder"
              :required="true"
            />
          </div>

          <div class="form-group">
            <NcTextArea
              v-model="headerDialog.description"
              :label="strings.headerDescription"
              :placeholder="strings.headerDescriptionPlaceholder"
              :rows="2"
            />
          </div>
        </div>

        <template #actions>
          <NcButton @click="headerDialog.show = false">
            {{ strings.cancel }}
          </NcButton>
          <NcButton variant="primary" :disabled="!headerDialog.name.trim()" @click="saveHeader">
            <template v-if="headerDialog.submitting" #icon>
              <NcLoadingIcon :size="20" />
            </template>
            {{ headerDialog.isEditing ? strings.update : strings.create }}
          </NcButton>
        </template>
      </NcDialog>
    </div>
  </PageWrapper>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import PageWrapper from '@/components/PageWrapper.vue'
import AppToolbar from '@/components/AppToolbar.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import PlusIcon from '@icons/Plus.vue'
import PencilIcon from '@icons/Pencil.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import type { Category, CatHeader, Role } from '@/types'

export default defineComponent({
  name: 'AdminCategoryEdit',
  components: {
    NcButton,
    NcDialog,
    NcEmptyContent,
    NcLoadingIcon,
    NcSelect,
    NcTextField,
    NcTextArea,
    PageWrapper,
    AppToolbar,
    ArrowLeftIcon,
    PlusIcon,
    PencilIcon,
  },
  data() {
    return {
      loading: false,
      submitting: false,
      error: null as string | null,
      headers: [] as CatHeader[],
      roles: [] as Role[],
      selectedHeader: null as { id: number; label: string } | null,
      selectedViewRoles: [] as Array<{ id: number; label: string }>,
      selectedModerateRoles: [] as Array<{ id: number; label: string }>,
      formData: {
        headerId: null as number | null,
        name: '',
        slug: '',
        description: '',
      },
      headerDialog: {
        show: false,
        isEditing: false,
        submitting: false,
        id: null as number | null,
        name: '',
        description: '',
      },

      strings: {
        back: t('forum', 'Back'),
        createCategory: t('forum', 'Create Category'),
        editCategory: t('forum', 'Edit Category'),
        subtitle: t('forum', 'Configure category details'),
        loading: t('forum', 'Loading…'),
        errorTitle: t('forum', 'Error loading category'),
        retry: t('forum', 'Retry'),
        basicInfo: t('forum', 'Basic Information'),
        categoryHeader: t('forum', 'Category Header'),
        selectHeader: t('forum', '-- Select a header --'),
        name: t('forum', 'Name'),
        namePlaceholder: t('forum', 'Enter category name'),
        slug: t('forum', 'Slug'),
        slugPlaceholder: t('forum', 'category-slug'),
        slugHelp: t('forum', 'URL-friendly identifier (e.g., "general-discussion")'),
        description: t('forum', 'Description'),
        descriptionPlaceholder: t('forum', 'Enter category description (optional)'),
        sortOrder: t('forum', 'Sort Order'),
        sortOrderPlaceholder: t('forum', '0'),
        sortOrderHelp: t('forum', 'Lower numbers appear first'),
        cancel: t('forum', 'Cancel'),
        create: t('forum', 'Create'),
        update: t('forum', 'Update'),
        newHeader: t('forum', 'New'),
        editHeader: t('forum', 'Edit'),
        createHeaderTitle: t('forum', 'Create Category Header'),
        editHeaderTitle: t('forum', 'Edit Category Header'),
        headerName: t('forum', 'Header Name'),
        headerNamePlaceholder: t('forum', 'Enter header name'),
        headerDescription: t('forum', 'Header Description'),
        headerDescriptionPlaceholder: t('forum', 'Enter header description (optional)'),
        headerSortOrder: t('forum', 'Sort Order'),
        permissions: t('forum', 'Permissions'),
        permissionsDescription: t(
          'forum',
          'Control which roles can access and moderate this category',
        ),
        viewRoles: t('forum', 'Roles that can view'),
        viewRolesHelp: t('forum', 'Select roles that can view this category and its threads'),
        moderateRoles: t('forum', 'Roles that can moderate'),
        moderateRolesHelp: t(
          'forum',
          'Select roles that can moderate (edit/delete) content in this category',
        ),
        selectRoles: t('forum', 'Select roles...'),
      },
    }
  },
  computed: {
    isEditing(): boolean {
      return !!this.$route.params.id
    },
    categoryId(): number | null {
      return this.$route.params.id ? parseInt(this.$route.params.id as string) : null
    },
    canSubmit(): boolean {
      return (
        this.selectedHeader !== null &&
        this.formData.name.trim().length > 0 &&
        this.formData.slug.trim().length > 0
      )
    },
    headerOptions(): Array<{ id: number; label: string }> {
      return this.headers.map((header) => ({
        id: header.id,
        label: header.name,
      }))
    },
    roleOptions(): Array<{ id: number; label: string }> {
      return this.roles.map((role) => ({
        id: role.id,
        label: role.name,
      }))
    },
  },
  watch: {
    selectedHeader(newVal: { id: number; label: string } | null) {
      this.formData.headerId = newVal?.id || null
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

        // Load category headers
        const headersResponse = await ocs.get<CatHeader[]>('/headers')
        this.headers = headersResponse.data || []

        // Load roles
        const rolesResponse = await ocs.get<Role[]>('/roles')
        this.roles = rolesResponse.data || []

        // If editing, load category data and permissions
        if (this.isEditing && this.categoryId) {
          await this.loadCategory()
          await this.loadPermissions()
        }
      } catch (e) {
        console.error('Failed to load category', e)
        this.error = (e as Error).message || t('forum', 'An unexpected error occurred')
      } finally {
        this.loading = false
      }
    },

    async loadCategory(): Promise<void> {
      if (!this.categoryId) return

      const categoryResponse = await ocs.get<Category>(`/categories/${this.categoryId}`)
      const category = categoryResponse.data

      this.formData.headerId = category.headerId
      this.formData.name = category.name
      this.formData.slug = category.slug
      this.formData.description = category.description || ''

      // Set selectedHeader based on headerId
      const header = this.headers.find((h) => h.id === category.headerId)
      if (header) {
        this.selectedHeader = {
          id: header.id,
          label: header.name,
        }
      }
    },

    async loadPermissions(): Promise<void> {
      if (!this.categoryId) return

      try {
        const permsResponse = await ocs.get<
          Array<{
            id: number
            categoryId: number
            roleId: number
            canView: boolean
            canModerate: boolean
          }>
        >(`/categories/${this.categoryId}/permissions`)

        const perms = permsResponse.data || []

        // Map permissions to role selections
        const viewRoleIds = new Set<number>()
        const moderateRoleIds = new Set<number>()

        perms.forEach((perm) => {
          if (perm.canView) {
            viewRoleIds.add(perm.roleId)
          }
          if (perm.canModerate) {
            moderateRoleIds.add(perm.roleId)
          }
        })

        // Set selected roles
        this.selectedViewRoles = this.roles
          .filter((role) => viewRoleIds.has(role.id))
          .map((role) => ({ id: role.id, label: role.name }))

        this.selectedModerateRoles = this.roles
          .filter((role) => moderateRoleIds.has(role.id))
          .map((role) => ({ id: role.id, label: role.name }))
      } catch (e) {
        console.error('Failed to load category permissions', e)
      }
    },

    async submitForm(): Promise<void> {
      if (!this.canSubmit) return

      try {
        this.submitting = true

        const categoryData = {
          headerId: this.formData.headerId!,
          name: this.formData.name.trim(),
          slug: this.formData.slug.trim(),
          description: this.formData.description.trim() || null,
        }

        let categoryId: number

        if (this.isEditing && this.categoryId !== null) {
          // Update existing category
          await ocs.put(`/categories/${this.categoryId}`, categoryData)
          categoryId = this.categoryId
        } else {
          // Create new category
          const response = await ocs.post<Category>('/categories', categoryData)
          categoryId = response.data.id
        }

        // Update permissions
        await this.updatePermissions(categoryId)

        // Navigate back to category list
        this.$router.push('/admin/categories')
      } catch (e) {
        console.error('Failed to save category', e)
        // TODO: Show error notification
      } finally {
        this.submitting = false
      }
    },

    async updatePermissions(categoryId: number): Promise<void> {
      // Build permissions array combining view and moderate roles
      const allRoleIds = new Set<number>()
      const viewRoleIds = new Set(this.selectedViewRoles.map((r) => r.id))
      const moderateRoleIds = new Set(this.selectedModerateRoles.map((r) => r.id))

      // Add all selected role IDs to the set
      this.selectedViewRoles.forEach((r) => allRoleIds.add(r.id))
      this.selectedModerateRoles.forEach((r) => allRoleIds.add(r.id))

      const permissionsData = Array.from(allRoleIds).map((roleId) => ({
        roleId,
        canView: viewRoleIds.has(roleId),
        canModerate: moderateRoleIds.has(roleId),
      }))

      await ocs.post(`/categories/${categoryId}/permissions`, {
        permissions: permissionsData,
      })
    },

    goBack(): void {
      this.$router.push('/admin/categories')
    },

    createNewHeader(): void {
      this.headerDialog.show = true
      this.headerDialog.isEditing = false
      this.headerDialog.id = null
      this.headerDialog.name = ''
      this.headerDialog.description = ''
    },

    editHeader(): void {
      if (!this.selectedHeader) return

      const header = this.headers.find((h) => h.id === this.selectedHeader?.id)
      if (!header) return

      this.headerDialog.show = true
      this.headerDialog.isEditing = true
      this.headerDialog.id = header.id
      this.headerDialog.name = header.name
      this.headerDialog.description = header.description || ''
    },

    async saveHeader(): Promise<void> {
      if (!this.headerDialog.name.trim()) return

      try {
        this.headerDialog.submitting = true

        const headerData = {
          name: this.headerDialog.name.trim(),
          description: this.headerDialog.description.trim() || null,
        }

        let headerId: number

        if (this.headerDialog.isEditing && this.headerDialog.id !== null) {
          // Update existing header
          await ocs.put(`/headers/${this.headerDialog.id}`, headerData)
          headerId = this.headerDialog.id

          // Update in local headers array
          const index = this.headers.findIndex((h) => h.id === headerId)
          if (index !== -1 && this.headers[index]) {
            this.headers[index] = {
              id: this.headers[index].id,
              sortOrder: this.headers[index].sortOrder,
              createdAt: this.headers[index].createdAt,
              name: headerData.name,
              description: headerData.description,
            }
          }
        } else {
          // Create new header
          const response = await ocs.post<CatHeader>('/headers', headerData)
          headerId = response.data.id

          // Add to local headers array
          this.headers.push(response.data)
        }

        // Auto-select the new/updated header
        const header = this.headers.find((h) => h.id === headerId)
        if (header) {
          this.selectedHeader = {
            id: header.id,
            label: header.name,
          }
        }

        this.headerDialog.show = false
      } catch (e) {
        console.error('Failed to save header', e)
        // TODO: Show error notification
      } finally {
        this.headerDialog.submitting = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.admin-category-edit {
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

  .category-form {
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
        margin-bottom: 4px;
      }

      .help-text {
        font-size: 0.85rem;
        margin-top: 4px;
      }

      .header-select-row {
        display: flex;
        gap: 8px;
        align-items: flex-start;

        .header-select {
          flex: 1;
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

.header-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 8px 0;

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;

    .help-text {
      font-size: 0.85rem;
      margin-top: 4px;
      color: var(--color-text-maxcontrast);
    }
  }
}
</style>
