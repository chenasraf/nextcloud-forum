<template>
  <div class="user-info-component" :class="{ 'layout-inline': layout === 'inline' }">
    <UserAvatar
      :user-id="userId"
      :display-name="displayName"
      :size="avatarSize"
      :is-deleted="isDeleted"
      :clickable="clickable"
    />
    <div class="user-details" :class="{ 'details-inline': layout === 'inline' }">
      <div class="name-and-meta">
        <span
          v-if="!isDeleted"
          class="user-name"
          :class="{ clickable: isClickable }"
          @click="handleNameClick"
        >
          {{ displayName || userId }}
        </span>
        <span v-else class="user-name deleted-user">
          {{ displayName || userId }}
        </span>
        <div v-if="showRoles && displayRoles.length > 0" class="role-badges">
          <RoleBadge v-for="role in displayRoles" :key="role.id" :role="role" density="compact" />
        </div>
        <template v-if="layout === 'inline'">
          <span class="meta-separator">·</span>
          <span class="meta-content">
            <slot name="meta"></slot>
          </span>
        </template>
      </div>
      <slot v-if="layout !== 'inline'" name="meta"></slot>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import UserAvatar from './UserAvatar.vue'
import RoleBadge from './RoleBadge.vue'
import type { Role } from '@/types'

export default defineComponent({
  name: 'UserInfo',
  components: {
    UserAvatar,
    RoleBadge,
  },
  props: {
    userId: {
      type: String,
      required: true,
    },
    displayName: {
      type: String,
      default: '',
    },
    avatarSize: {
      type: Number,
      default: 32,
    },
    isDeleted: {
      type: Boolean,
      default: false,
    },
    clickable: {
      type: Boolean,
      default: true,
    },
    layout: {
      type: String as () => 'column' | 'inline',
      default: 'column',
      validator: (value: string) => ['column', 'inline'].includes(value),
    },
    roles: {
      type: Array as PropType<Role[]>,
      default: () => [],
    },
    showRoles: {
      type: Boolean,
      default: true,
    },
  },
  computed: {
    isClickable(): boolean {
      return this.clickable && !this.isDeleted
    },

    displayRoles(): Role[] {
      if (!this.roles || this.roles.length === 0) {
        return []
      }

      // Define default role IDs and their precedence
      const defaultRoleIds = [1, 2, 3] // Admin (1), Moderator (2), User (3)
      const rolePrecedence: Record<number, number> = {
        1: 1, // Admin - highest priority
        2: 2, // Moderator - medium priority
        3: 3, // User - lowest priority
      }

      // Separate default and custom roles
      const defaultRoles = this.roles.filter((role) => defaultRoleIds.includes(role.id))
      const customRoles = this.roles.filter((role) => !defaultRoleIds.includes(role.id))

      // Find the most prominent default role
      let primaryDefaultRole: Role | null = null
      if (defaultRoles.length > 0) {
        primaryDefaultRole = defaultRoles.reduce((mostProminent, currentRole) => {
          const currentPrecedence = rolePrecedence[currentRole.id] || 999
          const prominentPrecedence = rolePrecedence[mostProminent.id] || 999
          return currentPrecedence < prominentPrecedence ? currentRole : mostProminent
        })
      }

      // Build the display list: primary default role + all custom roles
      const result: Role[] = []
      if (primaryDefaultRole) {
        result.push(primaryDefaultRole)
      }
      result.push(...customRoles)

      return result
    },
  },
  methods: {
    handleNameClick(event: MouseEvent): void {
      if (this.isClickable) {
        event.stopPropagation()
        this.$router.push(`/u/${this.userId}`)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.user-info-component {
  display: flex;
  align-items: center;
  gap: 12px;

  // When there's metadata in the slot, align to flex-start (only for column layout)
  &:not(.layout-inline):has(.user-details > :nth-child(2)) {
    align-items: flex-start;
  }
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 4px;

  &.details-inline {
    flex-direction: row;
    align-items: center;
  }
}

.name-and-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.role-badges {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.user-name {
  font-weight: 600;
  color: var(--color-main-text);
  font-size: 1rem;

  &.clickable {
    cursor: pointer;
    transition: color 0.2s;

    &:hover {
      color: var(--color-primary-element);
    }
  }

  &.deleted-user {
    font-style: italic;
    opacity: 0.7;
  }
}

.meta-separator {
  color: var(--color-text-maxcontrast);
  opacity: 0.5;
  font-size: 0.85rem;
}

.meta-content {
  font-size: 0.85rem;
  color: var(--color-text-maxcontrast);
}
</style>
