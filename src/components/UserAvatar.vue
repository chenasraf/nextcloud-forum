<template>
  <div
    v-if="!isDeleted"
    class="user-avatar"
    :class="{ clickable: isClickable }"
    @click="handleClick"
  >
    <NcAvatar :user="userId" :size="size" />
  </div>
  <div v-else class="user-avatar">
    <NcAvatar :display-name="displayName" :size="size" />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

export default defineComponent({
  name: 'UserAvatar',
  components: {
    NcAvatar,
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
    size: {
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
  emits: ['click'],
  computed: {
    isClickable(): boolean {
      return this.clickable && !this.isDeleted
    },
  },
  methods: {
    handleClick(event: MouseEvent): void {
      if (this.isClickable) {
        event.stopPropagation()
        this.$emit('click', this.userId)
        this.$router.push(`/u/${this.userId}`)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.user-avatar {
  &.clickable {
    cursor: pointer !important;

    :deep(.avatardiv) {
      cursor: pointer !important;
    }

    :deep(.avatardiv *) {
      cursor: pointer !important;
    }

    &:hover {
      opacity: 0.8;
    }

    &:hover :deep(.avatardiv) {
      opacity: 0.8;
    }
  }
}
</style>
