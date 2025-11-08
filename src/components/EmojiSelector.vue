<template>
  <div class="emoji-selector">
    <!-- Quick emojis (default 5) -->
    <div class="quick-emojis">
      <button
        v-for="emoji in defaultEmojis"
        :key="emoji"
        class="emoji-button"
        :title="emoji"
        @click="$emit('select', emoji)"
      >
        {{ emoji }}
      </button>
    </div>

    <div class="divider"></div>

    <!-- All emojis grid -->
    <div class="all-emojis">
      <div class="emoji-grid">
        <button
          v-for="emoji in allEmojis"
          :key="emoji"
          class="emoji-button"
          :title="emoji"
          @click="$emit('select', emoji)"
        >
          {{ emoji }}
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'EmojiSelector',
  props: {
    defaultEmojis: {
      type: Array as () => string[],
      default: () => ['👍', '❤️', '😄', '🎉', '👏'],
    },
  },
  emits: ['select'],
  data() {
    return {
      // Common emojis organized by category
      allEmojis: [
        // Smileys & Emotion
        '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣',
        '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰',
        '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜',
        '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏',
        '😒', '😞', '😔', '😟', '😕', '🙁', '😣', '😖',
        '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡',
        '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰',
        '😥', '😓', '🤗', '🤔', '🤭', '🤫', '🤥', '😶',
        '😐', '😑', '😬', '🙄', '😯', '😦', '😧', '😮',
        '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴',
        // Gestures & Hands
        '👋', '🤚', '🖐', '✋', '🖖', '👌', '🤌', '🤏',
        '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆',
        '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛',
        '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏',
        // Hearts & Love
        '💛', '💙', '💜', '🧡', '💚', '🖤', '🤍', '🤎',
        '❤️', '💔', '❣️', '💕', '💞', '💓', '💗', '💖',
        '💘', '💝', '💟',
        // Symbols
        '🎉', '🎊', '🎈', '🎁', '🏆', '🥇', '🥈', '🥉',
        '⭐', '🌟', '✨', '💫', '🔥', '💯', '✅', '❌',
        '⚠️', '❗', '❓', '💬', '💭', '👀',
      ],
    }
  },
})
</script>

<style scoped lang="scss">
.emoji-selector {
  display: flex;
  flex-direction: column;
  gap: 12px;

  .quick-emojis {
    display: flex;
    gap: 8px;
    justify-content: center;
    padding-bottom: 8px;
  }

  .divider {
    height: 1px;
    background: var(--color-border);
    width: 100%;
  }

  .all-emojis {
    max-height: 400px;
    overflow-y: auto;
    padding: 0 4px;

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
  }

  .emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
  }

  .emoji-button {
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

  .quick-emojis .emoji-button {
    font-size: 1.8rem;
    min-width: 48px;
    min-height: 48px;
    border: 1px solid var(--color-border);
    background: var(--color-main-background);

    &:hover {
      border-color: var(--color-primary-element);
    }
  }
}
</style>
