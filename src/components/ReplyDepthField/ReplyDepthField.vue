<template>
  <div class="reply-depth-field">
    <div class="field">
      <NcCheckboxRadioSwitch :model-value="enabled" type="switch" @update:model-value="setEnabled">
        {{ strings.enabled }}
      </NcCheckboxRadioSwitch>
      <p class="hint">{{ strings.enabledHint }}</p>
    </div>

    <div v-if="enabled" class="field">
      <label :for="inputId">{{ strings.depth }}</label>
      <NcTextField
        :id="inputId"
        :model-value="String(modelValue)"
        type="number"
        :min="1"
        :max="maxDepth"
        @update:model-value="setDepth"
      />
      <p class="hint">{{ strings.depthHint }}</p>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'

const DEFAULT_DEPTH = 5
const MAX_DEPTH = 10

/**
 * Edits a maximum reply nesting depth, where 0 turns nested replies off
 */
export default defineComponent({
  name: 'ReplyDepthField',
  components: {
    NcCheckboxRadioSwitch,
    NcTextField,
  },
  props: {
    modelValue: {
      type: Number,
      required: true,
    },
    inputId: {
      type: String,
      required: true,
    },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      maxDepth: MAX_DEPTH,
      // Restored when nested replies are turned back on
      lastDepth: this.modelValue > 0 ? this.modelValue : DEFAULT_DEPTH,
      strings: {
        enabled: t('forum', 'Allow nested replies'),
        enabledHint: t(
          'forum',
          'When enabled, people can reply to individual posts, and replies nest under the post they answer.',
        ),
        depth: t('forum', 'Maximum nesting depth'),
        depthHint: t(
          'forum',
          'How many levels replies can nest below a reply to the thread, from 1 to 10. Deeper replies are shown next to the post they answer.',
        ),
      },
    }
  },
  computed: {
    enabled(): boolean {
      return this.modelValue > 0
    },
  },
  watch: {
    modelValue(value: number) {
      if (value > 0) {
        this.lastDepth = value
      }
    },
  },
  methods: {
    setEnabled(enabled: boolean): void {
      this.$emit('update:modelValue', enabled ? this.lastDepth : 0)
    },
    setDepth(value: string | number): void {
      const depth = parseInt(String(value), 10)
      if (isNaN(depth)) {
        return
      }
      this.$emit('update:modelValue', Math.max(1, Math.min(MAX_DEPTH, depth)))
    },
  },
})
</script>

<style scoped lang="scss">
.reply-depth-field {
  display: flex;
  flex-direction: column;
  gap: 16px;

  label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--color-main-text);
  }

  .hint {
    margin: 6px 0 0 0;
    font-size: 0.85rem;
    color: var(--color-text-maxcontrast);
  }
}
</style>
