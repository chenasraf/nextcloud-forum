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
import { defineComponent } from 'vue'
import UserAvatar from './UserAvatar.vue'

export default defineComponent({
  name: 'UserInfo',
  components: {
    UserAvatar,
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
  },
  computed: {
    isClickable(): boolean {
      return this.clickable && !this.isDeleted
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
