<template>
  <div class="initialization-screen">
    <!-- Admin view -->
    <NcEmptyContent v-if="isAdmin" :name="strings.title" :description="strings.description">
      <template #icon>
        <CogIcon />
      </template>
      <template #action>
        <div class="init-form">
          <label class="init-label">{{ strings.selectLabel }}</label>
          <NcSelect
            v-model="selectedUsers"
            :options="adminUsers"
            :multiple="true"
            :loading="loadingUsers"
            :placeholder="strings.selectPlaceholder"
            label="displayName"
            track-by="id"
            class="init-select"
          />
          <NcNoteCard type="info" class="init-note">
            {{ strings.noteText }}
          </NcNoteCard>
          <NcButton
            variant="primary"
            :disabled="selectedUsers.length === 0 || initializing"
            @click="runInitialization"
          >
            <template v-if="initializing" #icon>
              <NcLoadingIcon :size="20" />
            </template>
            {{ initializing ? strings.initializingButton : strings.initializeButton }}
          </NcButton>
          <NcNoteCard v-if="errorMessage" type="error" class="init-note" role="alert">
            {{ errorMessage }}
          </NcNoteCard>
        </div>
      </template>
    </NcEmptyContent>

    <!-- Non-admin view -->
    <NcEmptyContent v-else :name="strings.nonAdminTitle" :description="strings.nonAdminDescription">
      <template #icon>
        <CogIcon />
      </template>
    </NcEmptyContent>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import CogIcon from '@icons/Cog.vue'
import { ocs } from '@/axios'

interface AdminUser {
  id: string
  displayName: string
}

export default defineComponent({
  name: 'InitializationScreen',
  components: {
    NcEmptyContent,
    NcSelect,
    NcNoteCard,
    NcButton,
    NcLoadingIcon,
    CogIcon,
  },
  emits: ['initialized'],
  setup(_, { emit }) {
    const currentUser = getCurrentUser()
    const isAdmin = computed(() => currentUser?.isAdmin === true)

    const adminUsers = ref<AdminUser[]>([])
    const selectedUsers = ref<AdminUser[]>([])
    const loadingUsers = ref(false)
    const initializing = ref(false)
    const errorMessage = ref<string | null>(null)

    const strings = {
      title: t('forum', 'Forum setup required'),
      description: t('forum', 'Select the accounts that should have the forum admin role.'),
      selectLabel: t('forum', 'Forum admin accounts:'),
      selectPlaceholder: t('forum', 'Select accounts …'),
      noteText: t('forum', 'All other accounts will receive the default role.'),
      initializeButton: t('forum', 'Initialize forum'),
      initializingButton: t('forum', 'Initializing …'),
      nonAdminTitle: t('forum', 'Forum not set up'),
      nonAdminDescription: t(
        'forum',
        'The forum has not been set up yet. Please contact an administration member to complete the setup.',
      ),
    }

    const fetchAdminUsers = async () => {
      loadingUsers.value = true
      try {
        const response = await ocs.get<AdminUser[]>('/init/admin-users')
        adminUsers.value = response.data
        selectedUsers.value = [...adminUsers.value]
      } catch (e) {
        console.error('Failed to fetch admin users:', e)
      } finally {
        loadingUsers.value = false
      }
    }

    const runInitialization = async () => {
      initializing.value = true
      errorMessage.value = null
      try {
        await ocs.post('/init/initialize', {
          adminUserIds: selectedUsers.value.map((u) => u.id),
        })
        emit('initialized')
      } catch (e: any) {
        errorMessage.value = e.response?.data?.message || e.message || 'Initialization failed'
      } finally {
        initializing.value = false
      }
    }

    // Fetch admin users on mount if admin
    if (isAdmin.value) {
      fetchAdminUsers()
    }

    return {
      isAdmin,
      adminUsers,
      selectedUsers,
      loadingUsers,
      initializing,
      errorMessage,
      strings,
      runInitialization,
    }
  },
})
</script>

<style scoped lang="scss">
.initialization-screen {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: 2rem;
}

.init-form {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  width: 100%;
  max-width: 400px;
}

.init-label {
  font-weight: bold;
  align-self: flex-start;
}

.init-select {
  width: 100%;
}

.init-note {
  width: 100%;
}
</style>
