<template>
  <div
    class="bbcode-editor-container"
    @dragenter="handleDragEnter"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <BBCodeToolbar
      ref="toolbar"
      :textarea-ref="contenteditableElement"
      @insert="handleBBCodeInsert"
    />
    <NcRichContenteditable
      v-if="isReady"
      :model-value="internalContent"
      :placeholder="placeholder"
      :disabled="disabled"
      :auto-complete="autoComplete"
      :user-data="userData"
      :multiline="true"
      @update:model-value="handleInput"
      @keydown="$emit('keydown', $event)"
      class="bbcode-editor-contenteditable"
      ref="contenteditable"
    />
    <NcNoteCard v-if="hasAttachmentBBCode" type="warning" class="attachment-disclaimer">
      <span v-html="strings.attachmentDisclaimer"></span>
    </NcNoteCard>

    <!-- Drag and Drop Overlay -->
    <div v-if="isDragging" class="drag-overlay">
      <div class="drag-overlay-content">
        <UploadIcon :size="48" />
        <p class="drag-overlay-text">{{ strings.dropFileHere }}</p>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import BBCodeToolbar from './BBCodeToolbar.vue'
import UploadIcon from '@icons/Upload.vue'
import { t } from '@nextcloud/l10n'
import { ocs } from '@/axios'

interface AutocompleteUser {
  id: string
  label: string
  icon: string
  source: string
}

interface UserData {
  [key: string]: {
    id: string
    label: string
    icon: string
    source: string
  }
}

export default defineComponent({
  name: 'BBCodeEditor',
  components: {
    NcRichContenteditable,
    NcNoteCard,
    BBCodeToolbar,
    UploadIcon,
  },
  props: {
    modelValue: {
      type: String,
      required: true,
    },
    placeholder: {
      type: String,
      default: '',
    },
    rows: {
      type: Number,
      default: 4,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    minHeight: {
      type: String,
      default: '9.1875rem',
    },
  },
  emits: ['update:modelValue', 'keydown'],
  data() {
    return {
      contenteditableElement: null as HTMLElement | null,
      isDragging: false,
      dragCounter: 0,
      userData: {} as UserData,
      isReady: false,
      // Internal content state - only set once on mount, then managed by contenteditable
      internalContent: '',
      strings: {
        attachmentDisclaimer: t(
          'forum',
          "{bStart}Please note:{bEnd} Attached files will be visible to anyone in the forum, regardless of the file's sharing settings.",
          { bStart: '<strong>', bEnd: '</strong>' },
          { escape: false },
        ),
        dropFileHere: t('forum', 'Drop file here to upload'),
      },
    }
  },
  computed: {
    hasAttachmentBBCode(): boolean {
      return /\[attachment[^\]]*\]/i.test(this.modelValue)
    },
  },
  watch: {
    // Sync internalContent when modelValue changes externally (e.g., BBCode toolbar insert, clear)
    // But skip if the change came from our own handleInput (to avoid cursor jumping)
    modelValue(newValue: string) {
      if (newValue !== this.internalContent) {
        this.internalContent = newValue
      }
    },
  },
  async created() {
    // Parse mentions from initial content before rendering
    await this.parseMentionsFromContent(this.modelValue)
    // Set initial content with cursor helper for mentions at end
    this.internalContent = this.addCursorHelperAfterMentions(this.modelValue)
    this.isReady = true
  },
  mounted() {
    this.updateContenteditableRef()
  },
  updated() {
    this.updateContenteditableRef()
  },
  methods: {
    updateContenteditableRef(): void {
      const contenteditable = this.$refs.contenteditable as any
      if (contenteditable?.$el) {
        // NcRichContenteditable uses a div with contenteditable
        const editableEl = contenteditable.$el.querySelector('[contenteditable]')
        if (editableEl) {
          this.contenteditableElement = editableEl as HTMLElement
        }
      }
    },

    /**
     * Add a zero-width space after mentions at the end of content
     * This allows the cursor to be placed after a mention for appending text
     * Only used when initially loading content, not during active editing
     */
    addCursorHelperAfterMentions(content: string): string {
      if (!content) return content

      // Add zero-width space after mentions at end of string
      // This ensures the cursor can be placed after mentions for appending
      const mentionAtEndPattern = /(@(?:"[^"]+"|[a-zA-Z0-9_.-]+))$/
      if (mentionAtEndPattern.test(content)) {
        return content + '\u200B'
      }

      return content
    },

    handleInput(value: string): void {
      // Update internal state
      this.internalContent = value
      // Remove zero-width spaces before emitting to parent (clean storage)
      const cleanValue = value.replace(/\u200B/g, '')
      this.$emit('update:modelValue', cleanValue)
    },

    handleBBCodeInsert(data: { text: string; cursorPos: number }): void {
      // Update the content with the new text
      this.$emit('update:modelValue', data.text)
      // The cursor position is handled by the BBCodeToolbar component
    },

    focus(): void {
      // Focus the contenteditable
      const contenteditable = this.$refs.contenteditable as any
      if (contenteditable?.$el) {
        const editableEl = contenteditable.$el.querySelector('[contenteditable]')
        if (editableEl) {
          editableEl.focus()
        }
      }
    },

    /**
     * Parse mentions from content and fetch user data for them
     * This ensures mentions display correctly when editing existing content
     */
    async parseMentionsFromContent(content: string): Promise<void> {
      if (!content) return

      // Pattern to match @"username with spaces" or @username
      const mentionPattern = /@(?:"([^"]+)"|([a-zA-Z0-9_.-]+))/g
      const userIds = new Set<string>()

      let match
      while ((match = mentionPattern.exec(content)) !== null) {
        // Get the username - either from quoted format or simple format
        const userId = match[1] || match[2]
        if (userId && !this.userData[userId]) {
          userIds.add(userId)
        }
      }

      if (userIds.size === 0) return

      // Fetch user data for each mentioned user and build new userData object
      const newUserData: UserData = { ...this.userData }

      for (const userId of userIds) {
        try {
          const response = await ocs.get<AutocompleteUser[]>('/users/autocomplete', {
            params: { search: userId, limit: 1 },
          })

          const users = response.data || []
          // Find exact match
          const user = users.find((u) => u.id === userId)
          if (user) {
            // Store with both formats to cover all cases
            newUserData[user.id] = {
              id: user.id,
              label: user.label,
              icon: user.icon,
              source: user.source,
            }
          }
        } catch (error) {
          console.error(`Error fetching user data for ${userId}:`, error)
        }
      }

      // Replace entire userData object to ensure reactivity
      this.userData = newUserData
    },

    async autoComplete(
      search: string,
      callback: (users: AutocompleteUser[]) => void,
    ): Promise<void> {
      try {
        const response = await ocs.get<AutocompleteUser[]>('/users/autocomplete', {
          params: { search, limit: 10 },
        })

        const users = response.data || []

        // Update userData with the fetched users for display
        const newUserData: UserData = { ...this.userData }
        users.forEach((user) => {
          newUserData[user.id] = {
            id: user.id,
            label: user.label,
            icon: user.icon,
            source: user.source,
          }
        })
        this.userData = newUserData

        callback(users)
      } catch (error) {
        console.error('Error fetching autocomplete users:', error)
        callback([])
      }
    },

    handleDragEnter(event: DragEvent): void {
      event.preventDefault()
      event.stopPropagation()

      // Only show overlay for file drags
      if (event.dataTransfer?.types.includes('Files')) {
        this.dragCounter++
        this.isDragging = true
      }
    },

    handleDragOver(event: DragEvent): void {
      event.preventDefault()
      event.stopPropagation()

      // Set the dropEffect to copy
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy'
      }
    },

    handleDragLeave(event: DragEvent): void {
      event.preventDefault()
      event.stopPropagation()

      this.dragCounter--
      if (this.dragCounter === 0) {
        this.isDragging = false
      }
    },

    async handleDrop(event: DragEvent): Promise<void> {
      event.preventDefault()
      event.stopPropagation()

      this.isDragging = false
      this.dragCounter = 0

      const files = event.dataTransfer?.files
      if (!files || files.length === 0) {
        return
      }

      // Get the first file
      const file = files[0]

      // Call the upload method from BBCodeToolbar
      const toolbar = this.$refs.toolbar as any
      if (toolbar && toolbar.uploadFile) {
        await toolbar.uploadFile(file)
      }
    },
  },
})
</script>

<style scoped lang="scss">
.bbcode-editor-container {
  position: relative;
  background: var(--color-background-hover);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  padding: 4px;
}

.bbcode-editor-contenteditable {
  margin-top: 0;

  :deep(.rich-contenteditable__input) {
    min-height: v-bind(minHeight) !important;
    padding: 8px 12px;
    background: var(--color-main-background);
    border: none;
    border-radius: 4px;
  }

  :deep([contenteditable]) {
    min-height: v-bind(minHeight) !important;
  }
}

.attachment-disclaimer {
  margin-top: 8px;
}

.drag-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--color-main-background);
  border: 3px dashed var(--color-primary-element);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  pointer-events: none;
  opacity: 0.95;

  .drag-overlay-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    color: var(--color-primary-element);

    .drag-overlay-text {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 600;
    }
  }
}
</style>
