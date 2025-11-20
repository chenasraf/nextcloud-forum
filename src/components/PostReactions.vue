<template>
  <div class="post-reactions">
    <!-- All reactions (default + custom) -->
    <button
      v-for="emoji in allVisibleEmojis"
      :key="emoji"
      class="reaction-button"
      :class="{ reacted: isReacted(emoji), 'has-count': getCount(emoji) > 0 }"
      :title="getReactionTooltip(emoji)"
      @click="handleToggleReaction(emoji)"
    >
      <span class="emoji">{{ emoji }}</span>
      <span v-if="getCount(emoji) > 0" class="count">{{ getCount(emoji) }}</span>
    </button>

    <!-- Add custom reaction button -->
    <LazyEmojiPicker @select="handleSelectEmoji" style="display: inline-block">
      <button class="add-reaction-button" :title="strings.addReaction">
        <span class="icon">+</span>
      </button>
    </LazyEmojiPicker>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { t, n } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { useReactions, type ReactionGroup } from '@/composables/useReactions'
import LazyEmojiPicker from '@/components/LazyEmojiPicker'

export default defineComponent({
  name: 'PostReactions',
  components: {
    LazyEmojiPicker,
  },
  props: {
    postId: {
      type: Number,
      required: true,
    },
    reactions: {
      type: Array as PropType<ReactionGroup[]>,
      default: () => [],
    },
  },
  emits: ['update'],
  setup() {
    const { toggleReaction } = useReactions()
    return { toggleReaction }
  },
  data() {
    return {
      defaultEmojis: ['👍', '❤️', '😄', '🎉', '👏'],
      reactionGroups: [...this.reactions] as ReactionGroup[],
      strings: {
        addReaction: t('forum', 'Add reaction'),
      },
    }
  },
  computed: {
    // All emojis to show: default emojis + custom emojis that have been used
    // Sorted by count (descending), with default emoji order preserved for equal counts
    allVisibleEmojis(): string[] {
      const customEmojis = this.reactionGroups
        .map((g) => g.emoji)
        .filter((emoji) => !this.defaultEmojis.includes(emoji))

      const allEmojis = [...this.defaultEmojis, ...customEmojis]

      // Sort by count (descending), preserving default order for equal counts
      return allEmojis.sort((a, b) => {
        const countA = this.getCount(a)
        const countB = this.getCount(b)

        // If counts differ, sort by count (descending)
        if (countA !== countB) {
          return countB - countA
        }

        // If counts are equal, preserve default emoji order
        const indexA = this.defaultEmojis.indexOf(a)
        const indexB = this.defaultEmojis.indexOf(b)

        // Both are default emojis - use their default order
        if (indexA !== -1 && indexB !== -1) {
          return indexA - indexB
        }

        // A is default, B is custom - default comes first
        if (indexA !== -1) {
          return -1
        }

        // B is default, A is custom - default comes first
        if (indexB !== -1) {
          return 1
        }

        // Both are custom - maintain current order (stable sort)
        return 0
      })
    },
  },
  watch: {
    reactions: {
      handler(newReactions) {
        this.reactionGroups = [...newReactions]
      },
      deep: true,
    },
  },
  methods: {
    handleSelectEmoji(emoji: string) {
      this.handleToggleReaction(emoji)
    },
    getCount(emoji: string): number {
      const group = this.reactionGroups.find((g) => g.emoji === emoji)
      return group ? group.count : 0
    },
    isReacted(emoji: string): boolean {
      const group = this.reactionGroups.find((g) => g.emoji === emoji)
      return group ? group.hasReacted : false
    },
    async handleToggleReaction(emoji: string) {
      const currentUser = getCurrentUser()
      if (!currentUser) {
        console.error('User not authenticated')
        return
      }

      try {
        const result = await this.toggleReaction(this.postId, emoji)

        // Update local state optimistically
        const existingGroup = this.reactionGroups.find((g) => g.emoji === emoji)

        if (result.action === 'added') {
          if (existingGroup) {
            existingGroup.count++
            existingGroup.hasReacted = true
            existingGroup.userIds.push(currentUser.uid)
          } else {
            this.reactionGroups.push({
              emoji,
              count: 1,
              userIds: [currentUser.uid],
              hasReacted: true,
            })
          }
        } else if (result.action === 'removed') {
          if (existingGroup) {
            existingGroup.count--
            existingGroup.hasReacted = false
            existingGroup.userIds = existingGroup.userIds.filter((id) => id !== currentUser.uid)

            // Remove group if count is 0 AND it's not a default emoji
            if (existingGroup.count === 0 && !this.defaultEmojis.includes(emoji)) {
              this.reactionGroups = this.reactionGroups.filter((g) => g.emoji !== emoji)
            }
          }
        }

        // Notify parent component of the update
        this.$emit('update', this.reactionGroups)
      } catch (error) {
        console.error('Failed to toggle reaction', error)
      }
    },
    getReactionTooltip(emoji: string): string {
      const count = this.getCount(emoji)
      const hasReacted = this.isReacted(emoji)

      if (count === 0) {
        return t('forum', 'React with {emoji}', { emoji })
      }

      if (count === 1) {
        return hasReacted
          ? t('forum', 'You reacted with {emoji}', { emoji })
          : t('forum', '1 person reacted with {emoji}', { emoji })
      }

      return hasReacted
        ? n(
            'forum',
            'You and %n other reacted with {emoji}',
            'You and %n others reacted with {emoji}',
            count - 1,
            { emoji },
          )
        : n('forum', '%n person reacted with {emoji}', '%n people reacted with {emoji}', count, {
            emoji,
          })
    },
  },
})
</script>

<style scoped lang="scss">
.post-reactions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  flex-wrap: wrap;

  .reaction-button {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.95rem;
    min-height: 30px;

    &:hover {
      background: var(--color-background-hover);
      border-color: var(--color-border-dark);
    }

    &:active {
      transform: scale(0.95);
    }

    // When the user has reacted
    &.reacted {
      background: var(--color-primary-element-light);
      border-color: var(--color-primary-element);

      &:hover {
        background: var(--color-primary-element-light-hover);
      }

      .count {
        font-weight: 600;
      }
    }

    // When there's no count (default emojis with 0 reactions)
    &:not(.has-count) {
      opacity: 0.7;

      &:hover {
        opacity: 1;
      }
    }

    .emoji {
      font-size: 1rem;
      line-height: 1;
    }

    .count {
      color: var(--color-main-text);
      font-size: 0.85rem;
      font-weight: 500;
      min-width: 8px;
      margin-left: 1ch;
    }
  }

  .add-reaction-button {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    min-width: 30px;
    min-height: 30px;
    border: 1px dashed var(--color-border);
    background: transparent;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    opacity: 0.6;

    &:hover {
      opacity: 1;
      background: var(--color-background-hover);
      border-color: var(--color-border-dark);
      border-style: solid;
    }

    &:active {
      transform: scale(0.95);
    }

    .icon {
      font-size: 1.2rem;
      line-height: 1;
      font-weight: bold;
      color: var(--color-text-maxcontrast);
    }

    &:hover .icon {
      color: var(--color-main-text);
    }
  }
}
</style>
