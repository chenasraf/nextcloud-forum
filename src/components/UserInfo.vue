<template>
  <div class="user-info-component">
    <UserAvatar
      :user-id="userId"
      :display-name="displayName"
      :size="avatarSize"
      :is-deleted="isDeleted"
      :clickable="clickable"
    />
    <div class="user-details">
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
      <slot name="meta"></slot>
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

  // When there's metadata in the slot, align to flex-start
  &:has(.user-details > :nth-child(2)) {
    align-items: flex-start;
  }
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
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
</style>
