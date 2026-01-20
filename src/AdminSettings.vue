<template>
  <div id="forum-settings" class="section">
    <h2>{{ strings.title }}</h2>

    <NcSettingsSection :name="strings.repairSeedsHeader">
      <NcNoteCard type="info">
        {{ strings.repairSeedsHelp }}
      </NcNoteCard>

      <div class="settings-section-content">
        <div class="repair-seeds-container">
          <NcButton :disabled="repairSeedsLoading" @click="runRepairSeeds">
            <template #icon>
              <WrenchIcon v-if="!repairSeedsLoading" :size="20" />
              <NcLoadingIcon v-else :size="20" />
            </template>
            {{ strings.runRepairSeeds }}
          </NcButton>

          <NcNoteCard v-if="repairSeedsResult" :type="repairSeedsSuccess ? 'success' : 'error'">
            <pre class="repair-seeds-output">{{ repairSeedsResult }}</pre>
          </NcNoteCard>
        </div>
      </div>
    </NcSettingsSection>

    <NcSettingsSection :name="strings.userRolesHeader">
      <NcNoteCard type="info">
        {{ strings.userRolesHelp }}
      </NcNoteCard>

      <div class="settings-section-content">
        <div class="user-role-form">
          <div class="field-row">
            <div class="form-group">
              <label for="user-id">{{ strings.userIdLabel }}</label>
              <NcTextField
                id="user-id"
                v-model="userId"
                :placeholder="strings.userIdPlaceholder"
                :disabled="assignRoleLoading"
              />
            </div>
            <div class="form-group">
              <label for="role-select">{{ strings.roleLabel }}</label>
              <NcSelect
                input-id="role-select"
                v-model="selectedRole"
                :options="roleOptions"
                :placeholder="strings.rolePlaceholder"
                :disabled="assignRoleLoading || rolesLoading"
                :loading="rolesLoading"
              />
            </div>
          </div>

          <div class="button-row">
            <NcButton
              variant="primary"
              :disabled="!canAssignRole || assignRoleLoading"
              @click="assignRole"
            >
              <template #icon>
                <PlusIcon v-if="!assignRoleLoading" :size="20" />
                <NcLoadingIcon v-else :size="20" />
              </template>
              {{ strings.assignRole }}
            </NcButton>
          </div>

          <NcNoteCard v-if="assignRoleResult" :type="assignRoleSuccess ? 'success' : 'error'">
            <p>{{ assignRoleResult }}</p>
          </NcNoteCard>
        </div>
      </div>
    </NcSettingsSection>
  </div>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import WrenchIcon from '@icons/Wrench.vue'
import PlusIcon from '@icons/Plus.vue'

import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

export default {
  name: 'AdminSettings',
  components: {
    NcSettingsSection,
    NcButton,
    NcSelect,
    NcNoteCard,
    NcTextField,
    NcLoadingIcon,
    WrenchIcon,
    PlusIcon,
  },
  data() {
    return {
      // Repair seeds
      repairSeedsLoading: false,
      repairSeedsResult: null,
      repairSeedsSuccess: false,

      // User roles
      rolesLoading: true,
      roles: [],
      userId: '',
      selectedRole: null,
      assignRoleLoading: false,
      assignRoleResult: null,
      assignRoleSuccess: false,

      strings: {
        title: t('forum', 'Forum'),
        repairSeedsHeader: t('forum', 'Repair Seeds'),
        repairSeedsHelp: t(
          'forum',
          'Run the repair seeds command to restore default forum data (roles, categories, permissions, BBCodes). This is safe to run multiple times as it will skip data that already exists.',
        ),
        runRepairSeeds: t('forum', 'Run Repair Seeds'),
        userRolesHeader: t('forum', 'User Roles'),
        userRolesHelp: t(
          'forum',
          'Assign forum roles to users. This allows you to grant administrative or moderator privileges to specific users.',
        ),
        userIdLabel: t('forum', 'User ID'),
        userIdPlaceholder: t('forum', 'Enter user ID'),
        roleLabel: t('forum', 'Role'),
        rolePlaceholder: t('forum', 'Select a role'),
        assignRole: t('forum', 'Assign Role'),
      },
    }
  },
  computed: {
    roleOptions() {
      return this.roles.map((role) => ({
        id: role.id,
        label: role.name,
      }))
    },
    canAssignRole() {
      return this.userId.trim() !== '' && this.selectedRole !== null
    },
  },
  created() {
    this.fetchRoles()
  },
  methods: {
    async fetchRoles() {
      try {
        this.rolesLoading = true
        const resp = await ocs.get('/admin/roles')
        this.roles = resp.data.roles
      } catch (e) {
        console.error('Failed to fetch roles', e)
      } finally {
        this.rolesLoading = false
      }
    },
    async runRepairSeeds() {
      try {
        this.repairSeedsLoading = true
        this.repairSeedsResult = null
        const resp = await ocs.post('/admin/repair-seeds')
        this.repairSeedsSuccess = resp.data.success
        this.repairSeedsResult = resp.data.message
        if (resp.data.success) {
          await this.fetchRoles()
        }
      } catch (e) {
        console.error('Failed to run repair seeds', e)
        this.repairSeedsSuccess = false
        this.repairSeedsResult =
          e.response?.data?.message || t('forum', 'Failed to run repair seeds')
      } finally {
        this.repairSeedsLoading = false
      }
    },
    async assignRole() {
      if (!this.canAssignRole) return

      try {
        this.assignRoleLoading = true
        this.assignRoleResult = null
        const resp = await ocs.post(
          `/admin/users/${encodeURIComponent(this.userId.trim())}/roles`,
          {
            roleId: this.selectedRole.id,
          },
        )
        this.assignRoleSuccess = resp.data.success
        this.assignRoleResult = resp.data.message
        if (resp.data.success) {
          // Clear form on success
          this.userId = ''
          this.selectedRole = null
        }
      } catch (e) {
        console.error('Failed to assign role', e)
        this.assignRoleSuccess = false
        this.assignRoleResult = e.response?.data?.message || t('forum', 'Failed to assign role')
      } finally {
        this.assignRoleLoading = false
      }
    },
  },
}
</script>

<style scoped lang="scss">
#forum-settings {
  h2:first-child {
    margin-top: 0;
  }

  .settings-section-content {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 16px;
    margin-top: 16px;
  }

  .repair-seeds-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 600px;
  }

  .repair-seeds-output {
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    font-family: monospace;
    font-size: 12px;
  }

  .user-role-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 600px;

    .field-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;

      .form-group {
        flex: 1;
        min-width: 200px;
        display: flex;
        flex-direction: column;
        gap: 4px;

        label {
          font-weight: bold;
        }
      }
    }

    .button-row {
      display: flex;
      gap: 12px;
    }
  }
}
</style>
