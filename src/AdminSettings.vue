<template>
  <div id="forum-settings" class="section">
    <h2>{{ strings.title }}</h2>

    <NcSettingsSection :name="strings.repairSeedsHeader">
      <NcNoteCard type="info">
        {{ strings.repairSeedsHelp }}
      </NcNoteCard>

      <div class="settings-section-content">
        <div class="task-container">
          <NcButton :disabled="repairSeeds.loading" @click="runRepairSeeds">
            <template #icon>
              <WrenchIcon v-if="!repairSeeds.loading" :size="20" />
              <NcLoadingIcon v-else :size="20" />
            </template>
            {{ strings.runRepairSeeds }}
          </NcButton>

          <NcNoteCard v-if="repairSeeds.result" :type="repairSeeds.success ? 'success' : 'error'">
            <pre class="task-output">{{ repairSeeds.result }}</pre>
          </NcNoteCard>
        </div>
      </div>
    </NcSettingsSection>

    <NcSettingsSection :name="strings.rebuildStatsHeader">
      <NcNoteCard type="info">
        {{ strings.rebuildStatsHelp }}
      </NcNoteCard>

      <div class="settings-section-content">
        <div class="task-container">
          <NcButton :disabled="rebuildStats.loading" @click="runRebuildStats">
            <template #icon>
              <ChartBoxIcon v-if="!rebuildStats.loading" :size="20" />
              <NcLoadingIcon v-else :size="20" />
            </template>
            {{ strings.runRebuildStats }}
          </NcButton>

          <NcNoteCard v-if="rebuildStats.result" :type="rebuildStats.success ? 'success' : 'error'">
            <pre class="task-output">{{ rebuildStats.result }}</pre>
          </NcNoteCard>
        </div>
      </div>
    </NcSettingsSection>

    <NcSettingsSection :name="strings.userRolesHeader">
      <NcNoteCard type="info">
        {{ strings.userRolesHelp }}
      </NcNoteCard>

      <NcNoteCard v-if="rolesError" type="error">
        {{ rolesError }}
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
                :disabled="assignRole.loading"
              />
            </div>
            <div class="form-group">
              <label for="role-select">{{ strings.roleLabel }}</label>
              <NcSelect
                input-id="role-select"
                v-model="selectedRole"
                :options="roleOptions"
                :placeholder="strings.rolePlaceholder"
                :disabled="assignRole.loading || rolesLoading"
                :loading="rolesLoading"
              />
            </div>
          </div>

          <div class="button-row">
            <NcButton
              variant="primary"
              :disabled="!canAssignRole || assignRole.loading"
              @click="runAssignRole"
            >
              <template #icon>
                <PlusIcon v-if="!assignRole.loading" :size="20" />
                <NcLoadingIcon v-else :size="20" />
              </template>
              {{ strings.assignRole }}
            </NcButton>
          </div>

          <NcNoteCard v-if="assignRole.result" :type="assignRole.success ? 'success' : 'error'">
            <p>{{ assignRole.result }}</p>
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
import ChartBoxIcon from '@icons/ChartBox.vue'
import PlusIcon from '@icons/Plus.vue'

import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'

function createTask() {
  return { loading: false, result: null, success: false }
}

async function runTask(task, fn, fallbackError) {
  try {
    task.loading = true
    task.result = null
    await fn(task)
  } catch (e) {
    console.error(fallbackError, e)
    task.success = false
    task.result =
      e.response?.data?.message || e.response?.data?.error || e.message || t('forum', fallbackError)
  } finally {
    task.loading = false
  }
}

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
    ChartBoxIcon,
    PlusIcon,
  },
  data() {
    return {
      repairSeeds: createTask(),
      rebuildStats: createTask(),
      assignRole: createTask(),

      // User roles
      rolesLoading: true,
      rolesError: null,
      roles: [],
      userId: '',
      selectedRole: null,

      strings: {
        title: t('forum', 'Forum'),
        repairSeedsHeader: t('forum', 'Database Initial Data'),
        repairSeedsHelp: t(
          'forum',
          'Restore default forum data (roles, categories, permissions, BBCodes). This is safe to run multiple times as it will skip data that already exists.',
        ),
        runRepairSeeds: t('forum', 'Repair Database Initial Data'),
        rebuildStatsHeader: t('forum', 'Rebuild Statistics'),
        rebuildStatsHelp: t(
          'forum',
          'Recalculate all forum statistics including account post counts, thread counts, and category counters. Use this if statistics appear incorrect or out of sync.',
        ),
        runRebuildStats: t('forum', 'Rebuild Statistics'),
        userRolesHeader: t('forum', 'User Roles'),
        userRolesHelp: t(
          'forum',
          'Assign forum roles to accounts. This allows you to grant administrative or moderator privileges to specific accounts.',
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
        this.rolesError = null
        const resp = await ocs.get('/admin/roles')
        this.roles = resp.data.roles
      } catch (e) {
        console.error('Failed to fetch roles', e)
        this.rolesError = e.response?.data?.message || t('forum', 'Failed to fetch roles')
      } finally {
        this.rolesLoading = false
      }
    },
    runRepairSeeds() {
      return runTask(
        this.repairSeeds,
        async (task) => {
          const resp = await ocs.post('/admin/repair-seeds')
          task.success = resp.data.success
          task.result = resp.data.message
          if (resp.data.success) {
            await this.fetchRoles()
          }
        },
        'Failed to run repair database initial data',
      )
    },
    runRebuildStats() {
      return runTask(
        this.rebuildStats,
        async (task) => {
          const resp = await ocs.post('/admin/rebuild-stats')
          task.success = resp.data.success
          task.result = resp.data.message
        },
        'Failed to rebuild statistics',
      )
    },
    runAssignRole() {
      if (!this.canAssignRole) return
      return runTask(
        this.assignRole,
        async (task) => {
          const resp = await ocs.post(
            `/admin/users/${encodeURIComponent(this.userId.trim())}/roles`,
            { roleId: this.selectedRole.id },
          )
          task.success = resp.data.success
          task.result = resp.data.message
          if (resp.data.success) {
            this.userId = ''
            this.selectedRole = null
          }
        },
        'Failed to assign role',
      )
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

  .task-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 600px;
  }

  .task-output {
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
