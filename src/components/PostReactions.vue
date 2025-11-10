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
    <div class="add-reaction">
      <button
        class="add-reaction-button"
        :class="{ open: showPicker }"
        :title="strings.addReaction"
        @click="togglePicker"
      >
        <span class="icon">+</span>
      </button>

      <!-- Emoji picker -->
      <Transition name="fade">
        <div v-if="showPicker" class="emoji-picker-overlay" @click="closePicker">
          <div class="emoji-picker-container" @click.stop>
            <div class="emoji-picker-content">
              <h3>{{ strings.pickEmoji }}</h3>
              <div class="emoji-categories">
                <div v-for="group in emojiGroups" :key="group.name" class="emoji-category">
                  <h4 class="category-header">{{ group.name }}</h4>
                  <div class="emoji-grid">
                    <button
                      v-for="item in group.emojis"
                      :key="item.emoji"
                      class="emoji-option"
                      :title="item.title"
                      @click="handleSelectEmoji(item.emoji)"
                    >
                      {{ item.emoji }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { t, n } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { useReactions, type ReactionGroup } from '@/composables/useReactions'
import { EMOJI_GROUPS } from '@/constants/emojis'

export default defineComponent({
  name: 'PostReactions',
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
      showPicker: false,
      strings: {
        addReaction: t('forum', 'Add reaction'),
        pickEmoji: t('forum', 'Pick an emoji'),
      },
      emojiGroups: EMOJI_GROUPS,
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
    togglePicker() {
      this.showPicker = !this.showPicker
    },
    closePicker() {
      this.showPicker = false
    },
    handleSelectEmoji(emoji: string) {
      this.handleToggleReaction(emoji)
      this.closePicker()
    },
    getEmojiTitle(emoji: string): string | null {
      // Find the emoji title from the emoji groups
      for (const group of this.emojiGroups) {
        const item = group.emojis.find((e) => e.emoji === emoji)
        if (item) {
          return item.title
        }
      }
      return null
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
      const title = this.getEmojiTitle(emoji) ?? emoji

      if (count === 0) {
        return t('forum', 'React with {title}', { title })
      }

      if (count === 1) {
        return hasReacted
          ? t('forum', 'You reacted with {title}', { title })
          : t('forum', '1 person reacted with {title}', { title })
      }

      return hasReacted
        ? n(
            'forum',
            'You and %n other reacted with {title}',
            'You and %n others reacted with {title}',
            count - 1,
            { title },
          )
        : n('forum', '%n person reacted with {title}', '%n people reacted with {title}', count, {
            title,
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

  .add-reaction {
    position: relative;
    display: flex;
    align-items: center;

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

      &.open {
        opacity: 1;
        background: var(--color-background-hover);
        border-color: var(--color-primary-element);
        border-style: solid;
      }

      .icon {
        font-size: 1.2rem;
        line-height: 1;
        font-weight: bold;
        color: var(--color-text-maxcontrast);
      }

      &:hover .icon,
      &.open .icon {
        color: var(--color-main-text);
      }
    }

    .emoji-picker-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 9999;
      background: rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(2px);
      display: flex;
      align-items: center;
      justify-content: center;

      .emoji-picker-container {
        background: var(--color-main-background);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        max-width: 90vw;
        max-height: 80vh;
        overflow: hidden;

        .emoji-picker-content {
          padding: 20px;

          h3 {
            margin: 0 0 16px 0;
            font-size: 1.1rem;
            color: var(--color-main-text);
          }

          .emoji-categories {
            max-height: 500px;
            overflow-y: auto;
            padding: 4px;

            &::-webkit-scrollbar {
              width: 8px;
            }

            &::-webkit-scrollbar-track {
              background: var(--color-background-dark);
              border-radius: 4px;
            }

            &::-webkit-scrollbar-thumb {
              background: var(--color-border-dark);
              border-radius: 4px;

              &:hover {
                background: var(--color-text-maxcontrast);
              }
            }

            .emoji-category {
              margin-bottom: 20px;

              &:last-child {
                margin-bottom: 0;
              }

              .category-header {
                margin: 0 0 12px 0;
                font-size: 0.9rem;
                font-weight: 600;
                color: var(--color-text-maxcontrast);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding-left: 4px;
              }

              .emoji-grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 4px;

                .emoji-option {
                  border: 1px solid transparent;
                  background: transparent;
                  border-radius: 8px;
                  padding: 8px;
                  font-size: 1.5rem;
                  cursor: pointer;
                  transition: all 0.15s ease;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  min-width: 40px;
                  min-height: 40px;

                  &:hover {
                    background: var(--color-background-hover);
                    border-color: var(--color-border);
                    transform: scale(1.15);
                  }

                  &:active {
                    transform: scale(0.9);
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}

// Transition animations
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
