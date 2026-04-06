<template>
  <NcDialog
    :name="strings.title"
    :open="open"
    size="normal"
    close-on-click-outside
    @update:open="handleClose"
  >
    <div class="guest-reassign-dialog">
      <p class="description">
        {{ strings.description }}
      </p>

      <div class="user-search">
        <NcSelect
          v-model="selectedUser"
          :options="userOptions"
          :placeholder="strings.searchPlaceholder"
          :input-label="strings.searchLabel"
          :loading="searching"
          :filterable="false"
          label="label"
          @search="handleSearch"
        >
          <template #option="option">
            <div class="user-option">
              <NcAvatar :user="option.id" :size="24" :show-user-status="false" />
              <span class="user-option-label">{{ option.label }}</span>
              <span class="user-option-id muted">@{{ option.id }}</span>
            </div>
          </template>
          <template #selected-option="option">
            <div class="user-option">
              <NcAvatar :user="option.id" :size="20" :show-user-status="false" />
              <span class="user-option-label">{{ option.label }}</span>
            </div>
          </template>
          <template #no-options>
            {{ searchQuery ? strings.noResults : strings.typeToSearch }}
          </template>
        </NcSelect>
      </div>

      <!-- Error message -->
      <p v-if="error" class="error-message">{{ error }}</p>
    </div>

    <template #actions>
      <NcButton @click="handleClose">
        {{ strings.cancel }}
      </NcButton>
      <NcButton variant="primary" :disabled="!selectedUser || submitting" @click="handleConfirm">
        {{ submitting ? strings.reassigning : strings.confirm }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import { ocs } from '@/axios'
import { t } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'

interface UserOption {
  id: string
  label: string
}

export default defineComponent({
  name: 'GuestReassignDialog',
  components: {
    NcDialog,
    NcButton,
    NcSelect,
    NcAvatar,
  },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    guestAuthorId: {
      type: String,
      default: '',
    },
    guestDisplayName: {
      type: String,
      default: '',
    },
  },
  emits: ['update:open', 'reassigned'],
  data() {
    return {
      selectedUser: null as UserOption | null,
      userOptions: [] as UserOption[],
      searching: false,
      submitting: false,
      error: null as string | null,
      searchQuery: '',
      searchTimeout: null as ReturnType<typeof setTimeout> | null,
      strings: {
        title: t('forum', 'Assign guest posts to account'),
        description: t(
          'forum',
          'All posts and threads by this guest will be reassigned to the selected account.',
        ),
        searchPlaceholder: t('forum', 'Search for an account …'),
        searchLabel: t('forum', 'Account'),
        noResults: t('forum', 'No accounts found'),
        typeToSearch: t('forum', 'Type to search for an account'),
        cancel: t('forum', 'Cancel'),
        confirm: t('forum', 'Reassign'),
        reassigning: t('forum', 'Reassigning …'),
        successMessage: t('forum', 'Guest posts reassigned successfully'),
        errorMessage: t('forum', 'Failed to reassign guest posts'),
      },
    }
  },
  watch: {
    open(newVal: boolean) {
      if (newVal) {
        this.reset()
      }
    },
  },
  methods: {
    reset() {
      this.selectedUser = null
      this.userOptions = []
      this.error = null
      this.searchQuery = ''
    },

    handleClose() {
      this.$emit('update:open', false)
    },

    handleSearch(query: string) {
      this.searchQuery = query
      if (this.searchTimeout) {
        clearTimeout(this.searchTimeout)
      }
      if (!query || query.length < 1) {
        this.userOptions = []
        return
      }
      this.searchTimeout = setTimeout(() => {
        this.fetchUsers(query)
      }, 300)
    },

    async fetchUsers(query: string) {
      try {
        this.searching = true
        const response = await ocs.get<Array<{ id: string; label: string }>>(
          '/users/autocomplete',
          {
            params: { search: query, limit: 10 },
          },
        )
        this.userOptions = (response.data || []).map((u) => ({
          id: u.id,
          label: u.label,
        }))
      } catch (e) {
        console.error('Error searching users:', e)
        this.userOptions = []
      } finally {
        this.searching = false
      }
    },

    async handleConfirm() {
      if (!this.selectedUser || !this.guestAuthorId) {
        return
      }

      try {
        this.submitting = true
        this.error = null

        await ocs.post('/admin/guests/reassign', {
          guestAuthorId: this.guestAuthorId,
          targetUserId: this.selectedUser.id,
        })

        showSuccess(this.strings.successMessage)
        this.$emit('reassigned', {
          guestAuthorId: this.guestAuthorId,
          targetUserId: this.selectedUser.id,
          targetDisplayName: this.selectedUser.label,
        })
        this.handleClose()
      } catch (e) {
        console.error('Error reassigning guest posts:', e)
        const errorData = (e as any)?.response?.data
        this.error = errorData?.error || this.strings.errorMessage
      } finally {
        this.submitting = false
      }
    },
  },
})
</script>

<style scoped lang="scss">
.guest-reassign-dialog {
  padding: 8px 0;

  .description {
    margin-bottom: 16px;
    color: var(--color-text-maxcontrast);
  }

  .user-search {
    width: 100%;
  }

  .user-option {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .user-option-id {
    font-size: 0.85rem;
  }

  .error-message {
    margin-top: 12px;
    color: var(--color-error);
    font-size: 0.9rem;
  }
}
</style>
