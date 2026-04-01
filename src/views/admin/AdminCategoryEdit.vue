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
        <FormSection :title="strings.basicInfo">
          <div class="form-grid">
            <div class="form-group">
              <label>{{ strings.parent }} *</label>
              <div class="header-select-row">
                <NcSelect
                  v-model="selectedParent"
                  :options="parentOptions"
                  :placeholder="strings.selectParent"
                  label="label"
                  track-by="id"
                  class="header-select"
                />
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
                :disabled="isEditing"
              />
              <p class="help-text muted">
                {{ isEditing ? strings.slugHelpLocked : strings.slugHelp }}
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

            <div class="form-group">
              <NcTextField
                v-model.number="formData.sortOrder"
                type="number"
                :label="strings.sortOrder"
                :placeholder="strings.sortOrderPlaceholder"
              />
              <p class="help-text muted">{{ strings.sortOrderHelp }}</p>
            </div>
          </div>
        </FormSection>

        <!-- Design Section -->
        <FormSection :title="strings.design" :subtitle="strings.designDesc">
          <div class="design-section">
            <div class="design-controls">
              <ColorPickerPreset
                v-model="formData.color"
                :presets="categoryColorPresets"
                :label="strings.categoryColor"
              />
              <div class="text-color-group">
                <label>{{ strings.textColor }}</label>
                <div class="text-color-options">
                  <NcCheckboxRadioSwitch
                    v-model="formData.textColor"
                    value="dark"
                    name="textColor"
                    type="radio"
                  >
                    {{ strings.darkText }}
                  </NcCheckboxRadioSwitch>
                  <NcCheckboxRadioSwitch
                    v-model="formData.textColor"
                    value="light"
                    name="textColor"
                    type="radio"
                  >
                    {{ strings.lightText }}
                  </NcCheckboxRadioSwitch>
                </div>
              </div>
              <div class="hide-children-group">
                <NcCheckboxRadioSwitch v-model="formData.hideChildrenOnCard">
                  {{ strings.hideChildrenOnCard }}
                </NcCheckboxRadioSwitch>
                <p class="help-text muted">{{ strings.hideChildrenOnCardHelp }}</p>
              </div>
            </div>
            <div class="design-preview">
              <label>{{ strings.preview }}</label>
              <CategoryCard :category="previewCategory" />
            </div>
          </div>
        </FormSection>

        <!-- Permissions Section -->
        <FormSection :title="strings.permissions" :subtitle="strings.permissionsDescription">
          <div class="form-grid">
            <div class="form-group">
              <label>{{ strings.viewRoles }}</label>
              <NcSelect
                v-model="selectedViewTargets"
                :options="viewTargetOptions"
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
              <label>{{ strings.postRoles }}</label>
              <NcSelect
                v-model="selectedPostTargets"
                :options="postTargetOptions"
                :placeholder="strings.selectRoles"
                label="label"
                track-by="id"
                :multiple="true"
                :taggable="false"
                :close-on-select="false"
              />
              <p class="help-text muted">{{ strings.postRolesHelp }}</p>
            </div>

            <div class="form-group">
              <label>{{ strings.replyRoles }}</label>
              <NcSelect
                v-model="selectedReplyTargets"
                :options="replyTargetOptions"
                :placeholder="strings.selectRoles"
                label="label"
                track-by="id"
                :multiple="true"
                :taggable="false"
                :close-on-select="false"
              />
              <p class="help-text muted">{{ strings.replyRolesHelp }}</p>
            </div>

            <div class="form-group">
              <label>{{ strings.moderateRoles }}</label>
              <NcSelect
                v-model="selectedModerateTargets"
                :options="moderateTargetOptions"
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
        </FormSection>

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
import PageWrapper from '@/components/PageWrapper'
import AppToolbar from '@/components/AppToolbar'
import FormSection from '@/components/FormSection'
import CategoryCard from '@/components/CategoryCard'
import ColorPickerPreset from '@/components/ColorPickerPreset'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { isAdminRole, isModeratorRole, isDefaultRole, isGuestRole } from '@/constants'
import { useCategories } from '@/composables/useCategories'
import type { Category, CategoryPerm, CatHeader, Role, Team } from '@/types'
import PageHeader from '@/components/PageHeader'

type PermTarget = { id: string; label: string; type: 'role' | 'team' }
type ParentOption = { id: string; label: string; type: 'header' | 'category' }

export default defineComponent({
  name: 'AdminCategoryEdit',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcEmptyContent,
    NcLoadingIcon,
    NcSelect,
    NcTextField,
    NcTextArea,
    PageWrapper,
    AppToolbar,
    FormSection,
    CategoryCard,
    ColorPickerPreset,
    ArrowLeftIcon,
    PageHeader,
  },
  setup() {
    const {
      categoryHeaders,
      fetchCategories,
      refresh: refreshCategories,
      getAllCategoriesFlat,
    } = useCategories()
    return {
      categoryHeaders,
      fetchCategories,
      refreshCategories,
      getAllCategoriesFlat,
    }
  },
  data() {
    return {
      loading: false,
      submitting: false,
      error: null as string | null,
      headers: [] as CatHeader[],
      roles: [] as Role[],
      selectedParent: null as ParentOption | null,
      teams: [] as Team[],
      selectedViewTargets: [] as PermTarget[],
      selectedPostTargets: [] as PermTarget[],
      selectedReplyTargets: [] as PermTarget[],
      selectedModerateTargets: [] as PermTarget[],
      formData: {
        headerId: null as number | null,
        parentId: null as number | null,
        name: '',
        slug: '',
        description: '',
        sortOrder: 0,
        color: null as string | null,
        textColor: 'dark' as 'light' | 'dark',
        hideChildrenOnCard: false,
      },
      slugManuallyEdited: false,

      strings: {
        back: t('forum', 'Back'),
        createCategory: t('forum', 'Create category'),
        editCategory: t('forum', 'Edit category'),
        subtitle: t('forum', 'Configure category details'),
        loading: t('forum', 'Loading …'),
        errorTitle: t('forum', 'Error loading category'),
        retry: t('forum', 'Retry'),
        basicInfo: t('forum', 'Basic information'),
        parent: t('forum', 'Parent'),
        selectParent: t('forum', '-- Select a parent --'),
        name: t('forum', 'Name'),
        namePlaceholder: t('forum', 'Enter category name'),
        slug: t('forum', 'Slug'),
        slugPlaceholder: 'category-slug',
        slugHelp: t('forum', 'URL-friendly identifier (e.g., "{slug}")', {
          slug: 'general-discussion',
        }),
        slugHelpLocked: t('forum', 'Slug cannot be changed after category creation'),
        description: t('forum', 'Description'),
        descriptionPlaceholder: t('forum', 'Enter category description (optional)'),
        sortOrder: t('forum', 'Sort order'),
        sortOrderPlaceholder: '0',
        sortOrderHelp: t('forum', 'Lower numbers appear first'),
        cancel: t('forum', 'Cancel'),
        create: t('forum', 'Create'),
        update: t('forum', 'Update'),
        permissions: t('forum', 'Permissions'),
        permissionsDescription: t(
          'forum',
          'Control which roles and teams can access and moderate this category',
        ),
        viewRoles: t('forum', 'Can view'),
        viewRolesHelp: t(
          'forum',
          'Select roles or teams that can view this category and its threads',
        ),
        postRoles: t('forum', 'Can post'),
        postRolesHelp: t(
          'forum',
          'Select roles or teams that can create new threads in this category',
        ),
        replyRoles: t('forum', 'Can reply'),
        replyRolesHelp: t(
          'forum',
          'Select roles or teams that can reply to threads in this category',
        ),
        moderateRoles: t('forum', 'Can moderate'),
        moderateRolesHelp: t(
          'forum',
          'Select roles or teams that can moderate (edit/delete) content in this category',
        ),
        selectRoles: t('forum', 'Select roles or teams …'),
        design: t('forum', 'Design'),
        designDesc: t('forum', 'Customize the appearance of this category'),
        categoryColor: t('forum', 'Category color'),
        textColor: t('forum', 'Text color'),
        darkText: t('forum', 'Dark text'),
        lightText: t('forum', 'Light text'),
        preview: t('forum', 'Preview'),
        hideChildrenOnCard: t('forum', 'Hide subcategories on category card'),
        hideChildrenOnCardHelp: t(
          'forum',
          "When enabled, child categories will not appear as links on this category's card on the home page",
        ),
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
        this.selectedParent !== null &&
        this.formData.name.trim().length > 0 &&
        this.formData.slug.trim().length > 0
      )
    },
    parentOptions(): ParentOption[] {
      const options: ParentOption[] = []
      // Get the set of descendant IDs to exclude (prevent circular refs)
      const excludeIds = new Set<number>()
      if (this.categoryId !== null) {
        excludeIds.add(this.categoryId)
        this.collectDescendantIds(this.categoryId, excludeIds)
      }

      for (const header of this.categoryHeaders) {
        // Add header as a parent option
        options.push({
          id: `header:${header.id}`,
          label: header.name,
          type: 'header',
        })
        // Add categories nested under this header (with indentation)
        if (header.categories) {
          this.addCategoryOptions(header.categories, options, excludeIds, 1)
        }
      }
      return options
    },
    teamOptions(): PermTarget[] {
      return this.teams.map((team) => ({
        id: `team:${team.id}`,
        label: `Team: ${team.displayName}`,
        type: 'team' as const,
      }))
    },
    viewTargetOptions(): PermTarget[] {
      const roleOptions: PermTarget[] = this.roles
        .filter((role) => !isAdminRole(role))
        .map((role) => ({
          id: `role:${role.id}`,
          label: role.name,
          type: 'role' as const,
        }))
      return [...roleOptions, ...this.teamOptions]
    },
    postTargetOptions(): PermTarget[] {
      const roleOptions: PermTarget[] = this.roles
        .filter((role) => !isAdminRole(role))
        .map((role) => ({
          id: `role:${role.id}`,
          label: role.name,
          type: 'role' as const,
        }))
      return [...roleOptions, ...this.teamOptions]
    },
    replyTargetOptions(): PermTarget[] {
      const roleOptions: PermTarget[] = this.roles
        .filter((role) => !isAdminRole(role))
        .map((role) => ({
          id: `role:${role.id}`,
          label: role.name,
          type: 'role' as const,
        }))
      return [...roleOptions, ...this.teamOptions]
    },
    moderateTargetOptions(): PermTarget[] {
      // Filter out Admin, Guest, and Default roles for moderation
      const roleOptions: PermTarget[] = this.roles
        .filter((role) => !isAdminRole(role) && !isGuestRole(role) && !isDefaultRole(role))
        .map((role) => ({
          id: `role:${role.id}`,
          label: role.name,
          type: 'role' as const,
        }))
      return [...roleOptions, ...this.teamOptions]
    },
    categoryColorPresets(): string[] {
      return [
        '#dc2626',
        '#ea580c',
        '#d97706',
        '#16a34a',
        '#059669',
        '#0891b2',
        '#2563eb',
        '#7c3aed',
        '#9333ea',
        '#db2777',
        '#4b5563',
        '#1e3a5f',
      ]
    },
    previewCategory(): Category {
      return {
        id: 0,
        headerId: 0,
        parentId: null,
        name: this.formData.name || this.strings.namePlaceholder,
        description: this.formData.description || this.strings.descriptionPlaceholder,
        slug: '',
        sortOrder: 0,
        color: this.formData.color,
        textColor: this.formData.color ? this.formData.textColor : null,
        hideChildrenOnCard: false,
        threadCount: 0,
        postCount: 0,
        createdAt: 0,
        updatedAt: 0,
      }
    },
  },
  watch: {
    selectedParent(newVal: ParentOption | null) {
      if (!newVal) {
        this.formData.headerId = null
        this.formData.parentId = null
        return
      }
      if (newVal.type === 'header') {
        this.formData.headerId = parseInt(newVal.id.split(':')[1])
        this.formData.parentId = null
      } else {
        this.formData.parentId = parseInt(newVal.id.split(':')[1])
        this.formData.headerId = null
      }

      // When creating a new category, auto-set sort order based on sibling count
      if (!this.isEditing && newVal) {
        if (newVal.type === 'header') {
          const header = this.categoryHeaders.find(
            (h) => h.id === parseInt(newVal.id.split(':')[1]),
          )
          this.formData.sortOrder = header?.categories?.length || 0
        } else {
          const allCats = this.getAllCategoriesFlat()
          const parentId = parseInt(newVal.id.split(':')[1])
          const siblings = allCats.filter((c) => c.parentId === parentId)
          this.formData.sortOrder = siblings.length
        }
      }
    },
    'formData.name'(newVal: string) {
      // Only auto-update slug when creating (not editing) and user hasn't manually edited it
      if (!this.isEditing && !this.slugManuallyEdited) {
        this.formData.slug = this.toKebabCase(newVal)
      }
    },
    'formData.slug'(newVal: string, oldVal: string) {
      // Only track manual edits when creating (not when editing existing category)
      if (!this.isEditing && newVal !== oldVal && newVal !== this.toKebabCase(this.formData.name)) {
        this.slugManuallyEdited = true
      }
      if (!newVal) {
        this.slugManuallyEdited = false
      }
    },
  },
  created() {
    this.refresh()
  },
  methods: {
    /** Recursively add category options with indentation */
    addCategoryOptions(
      categories: Category[],
      options: ParentOption[],
      excludeIds: Set<number>,
      depth: number,
    ): void {
      for (const cat of categories) {
        if (!excludeIds.has(cat.id)) {
          const indent = '\u00A0\u00A0\u00A0\u00A0'.repeat(depth)
          options.push({
            id: `category:${cat.id}`,
            label: `${indent}${cat.name}`,
            type: 'category',
          })
        }
        if (cat.children && cat.children.length > 0) {
          this.addCategoryOptions(cat.children, options, excludeIds, depth + 1)
        }
      }
    },

    /** Collect all descendant IDs of a category */
    collectDescendantIds(categoryId: number, result: Set<number>): void {
      const allCats = this.getAllCategoriesFlat()
      const children = allCats.filter((c) => c.parentId === categoryId)
      for (const child of children) {
        result.add(child.id)
        this.collectDescendantIds(child.id, result)
      }
    },

    toKebabCase(str: string): string {
      return str
        .trim()
        .toLowerCase()
        .replace(/[^\w\s-]/g, '') // Remove special characters
        .replace(/[\s_]+/g, '-') // Replace spaces and underscores with hyphens
        .replace(/^-+|-+$/g, '') // Remove leading/trailing hyphens
    },

    async refresh(): Promise<void> {
      try {
        this.loading = true
        this.error = null

        // Load categories with nested structure (includes headers and categories)
        await this.fetchCategories()

        // Extract headers from categoryHeaders
        this.headers = this.categoryHeaders.map((header) => ({
          id: header.id,
          name: header.name,
          description: header.description,
          sortOrder: header.sortOrder,
          createdAt: header.createdAt,
        }))

        // Load roles and teams in parallel
        const [rolesResponse, teamsResponse] = await Promise.all([
          ocs.get<Role[]>('/roles'),
          ocs.get<Team[]>('/teams').catch(() => ({ data: [] as Team[] })),
        ])
        this.roles = rolesResponse.data || []
        this.teams = teamsResponse.data || []

        // If editing, load category data and permissions
        if (this.isEditing && this.categoryId) {
          await this.loadCategory()
          await this.loadPermissions()
        } else {
          // When creating a new category, prefill with default roles
          // View, Post, Reply: Default user role
          const memberRole = this.roles.find(isDefaultRole)
          if (memberRole) {
            const memberOption: PermTarget = {
              id: `role:${memberRole.id}`,
              label: memberRole.name,
              type: 'role',
            }
            this.selectedViewTargets = [memberOption]
            this.selectedPostTargets = [memberOption]
            this.selectedReplyTargets = [memberOption]
          }

          // Moderate: Moderator only (admin has hardcoded full access)
          const moderatorRole = this.roles.find(isModeratorRole)
          this.selectedModerateTargets = []
          if (moderatorRole) {
            this.selectedModerateTargets.push({
              id: `role:${moderatorRole.id}`,
              label: moderatorRole.name,
              type: 'role',
            })
          }
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
      this.formData.parentId = category.parentId
      this.formData.name = category.name
      this.formData.slug = category.slug
      this.formData.description = category.description || ''
      this.formData.sortOrder = category.sortOrder
      this.formData.color = category.color || null
      this.formData.textColor = category.textColor || 'dark'
      this.formData.hideChildrenOnCard = category.hideChildrenOnCard || false

      // When editing, don't track manual slug edits (slug is pre-populated from DB)
      this.slugManuallyEdited = false

      // Set selectedParent based on parentId or headerId
      if (category.parentId !== null) {
        // Find the parent category in the tree
        const allCats = this.getAllCategoriesFlat()
        const parentCat = allCats.find((c) => c.id === category.parentId)
        if (parentCat) {
          // Find depth for indentation
          const option = this.parentOptions.find((o) => o.id === `category:${category.parentId}`)
          this.selectedParent = option || {
            id: `category:${parentCat.id}`,
            label: parentCat.name,
            type: 'category',
          }
        }
      } else if (category.headerId !== null) {
        const header = this.headers.find((h) => h.id === category.headerId)
        if (header) {
          this.selectedParent = {
            id: `header:${header.id}`,
            label: header.name,
            type: 'header',
          }
        }
      }
    },

    async loadPermissions(): Promise<void> {
      if (!this.categoryId) return

      try {
        const permsResponse = await ocs.get<CategoryPerm[]>(
          `/categories/${this.categoryId}/permissions`,
        )

        const perms = permsResponse.data || []

        const rolePerms = perms.filter((p) => p.targetType === 'role')
        const teamPerms = perms.filter((p) => p.targetType === 'team')

        // Map role permissions to PermTarget (skip admin - has hardcoded full access)
        const mapRolePerm = (p: CategoryPerm): PermTarget | null => {
          const role = this.roles.find((r) => String(r.id) === p.targetId)
          if (!role || isAdminRole(role)) return null
          return { id: `role:${role.id}`, label: role.name, type: 'role' }
        }

        // Map team permissions to PermTarget
        const mapTeamPerm = (p: CategoryPerm): PermTarget | null => {
          const team = this.teams.find((t) => t.id === p.targetId)
          return team
            ? { id: `team:${team.id}`, label: `Team: ${team.displayName}`, type: 'team' }
            : null
        }

        this.selectedViewTargets = [
          ...rolePerms.filter((p) => p.canView).map(mapRolePerm),
          ...teamPerms.filter((p) => p.canView).map(mapTeamPerm),
        ].filter((o): o is PermTarget => o !== null)

        this.selectedPostTargets = [
          ...rolePerms.filter((p) => p.canPost).map(mapRolePerm),
          ...teamPerms.filter((p) => p.canPost).map(mapTeamPerm),
        ].filter((o): o is PermTarget => o !== null)

        this.selectedReplyTargets = [
          ...rolePerms.filter((p) => p.canReply).map(mapRolePerm),
          ...teamPerms.filter((p) => p.canReply).map(mapTeamPerm),
        ].filter((o): o is PermTarget => o !== null)

        this.selectedModerateTargets = [
          ...rolePerms
            .filter((p) => p.canModerate)
            .map((p) => {
              const role = this.roles.find((r) => String(r.id) === p.targetId)
              if (!role || isAdminRole(role) || isGuestRole(role) || isDefaultRole(role))
                return null
              return { id: `role:${role.id}`, label: role.name, type: 'role' } as PermTarget
            }),
          ...teamPerms.filter((p) => p.canModerate).map(mapTeamPerm),
        ].filter((o): o is PermTarget => o !== null)
      } catch (e) {
        console.error('Failed to load category permissions', e)
      }
    },

    async submitForm(): Promise<void> {
      if (!this.canSubmit) return

      try {
        this.submitting = true

        const categoryData: Record<string, unknown> = {
          name: this.formData.name.trim(),
          slug: this.formData.slug.trim(),
          description: this.formData.description.trim() || null,
          sortOrder: this.formData.sortOrder,
          color: this.formData.color || null,
          textColor: this.formData.color ? this.formData.textColor : null,
          hideChildrenOnCard: this.formData.hideChildrenOnCard,
        }

        // Set parent based on selection type
        if (this.formData.parentId !== null) {
          categoryData.parentId = this.formData.parentId
          categoryData.headerId = null
        } else {
          categoryData.headerId = this.formData.headerId
          categoryData.parentId = null
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

        // Refresh sidebar categories
        this.refreshCategories()

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
      const filterByType = (targets: PermTarget[], type: 'role' | 'team') =>
        new Set(targets.filter((t) => t.type === type).map((t) => t.id.split(':')[1]))

      // Role permissions
      const viewRoleIds = filterByType(this.selectedViewTargets, 'role')
      const postRoleIds = filterByType(this.selectedPostTargets, 'role')
      const replyRoleIds = filterByType(this.selectedReplyTargets, 'role')
      const moderateRoleIds = filterByType(this.selectedModerateTargets, 'role')

      const allRoleIds = new Set([
        ...viewRoleIds,
        ...postRoleIds,
        ...replyRoleIds,
        ...moderateRoleIds,
      ])

      const permissions: Array<{
        roleId: number
        canView: boolean
        canPost: boolean
        canReply: boolean
        canModerate: boolean
      }> = []

      for (const roleId of allRoleIds) {
        permissions.push({
          roleId: parseInt(roleId),
          canView: viewRoleIds.has(roleId),
          canPost: postRoleIds.has(roleId),
          canReply: replyRoleIds.has(roleId),
          canModerate: moderateRoleIds.has(roleId),
        })
      }

      // Team permissions
      const viewTeamIds = filterByType(this.selectedViewTargets, 'team')
      const postTeamIds = filterByType(this.selectedPostTargets, 'team')
      const replyTeamIds = filterByType(this.selectedReplyTargets, 'team')
      const moderateTeamIds = filterByType(this.selectedModerateTargets, 'team')

      const allTeamIds = new Set([
        ...viewTeamIds,
        ...postTeamIds,
        ...replyTeamIds,
        ...moderateTeamIds,
      ])

      const teamPermissions: Array<{
        teamId: string
        canView: boolean
        canPost: boolean
        canReply: boolean
        canModerate: boolean
      }> = []

      for (const teamId of allTeamIds) {
        teamPermissions.push({
          teamId,
          canView: viewTeamIds.has(teamId),
          canPost: postTeamIds.has(teamId),
          canReply: replyTeamIds.has(teamId),
          canModerate: moderateTeamIds.has(teamId),
        })
      }

      await ocs.post(`/categories/${categoryId}/permissions`, {
        permissions,
        teamPermissions,
      })
    },

    goBack(): void {
      this.$router.push('/admin/categories')
    },
  },
})
</script>

<style scoped lang="scss">
.admin-category-edit {
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

    .design-section {
      display: flex;
      flex-wrap: wrap;
      gap: 32px;
      margin-top: 12px;

      .design-controls {
        display: flex;
        flex-direction: column;
        gap: 20px;
        flex: 1;
        min-width: 200px;
      }

      .text-color-group {
        display: flex;
        flex-direction: column;
        gap: 8px;

        > label {
          font-weight: 600;
          font-size: 0.9rem;
          color: var(--color-main-text);
        }

        .text-color-options {
          display: flex;
          gap: 16px;
        }
      }

      .hide-children-group {
        display: flex;
        flex-direction: column;
        gap: 4px;

        .help-text {
          font-size: 0.85rem;
          margin-top: 2px;
        }
      }

      .design-preview {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
        min-width: 280px;

        > label {
          font-weight: 600;
          font-size: 0.9rem;
          color: var(--color-main-text);
        }

        .category-card {
          cursor: default;
          pointer-events: none;
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
