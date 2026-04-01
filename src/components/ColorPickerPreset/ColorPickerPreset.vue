<template>
  <div class="color-picker-preset">
    <label v-if="label" class="color-picker-label">{{ label }}</label>
    <NcColorPicker
      :model-value="modelValue"
      :palette="presets"
      :advanced-fields="true"
      @update:model-value="$emit('update:modelValue', $event)"
      @submit="$emit('update:modelValue', $event)"
    >
      <NcButton
        :aria-label="modelValue ? strings.changeColor + ': ' + modelValue : strings.pickColor"
      >
        <template #icon>
          <div
            class="color-preview"
            :class="{ empty: !modelValue }"
            :style="modelValue ? { backgroundColor: modelValue } : {}"
            role="img"
            :aria-label="modelValue || strings.noColor"
          />
        </template>
        {{ modelValue || strings.pickColor }}
      </NcButton>
    </NcColorPicker>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcButton from '@nextcloud/vue/components/NcButton'
import { t } from '@nextcloud/l10n'

export default defineComponent({
  name: 'ColorPickerPreset',
  components: {
    NcColorPicker,
    NcButton,
  },
  props: {
    modelValue: {
      type: String as PropType<string | null>,
      default: null,
    },
    presets: {
      type: Array as PropType<string[]>,
      default: () => [],
    },
    label: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      strings: {
        pickColor: t('forum', 'Pick a color'),
        changeColor: t('forum', 'Change color'),
        noColor: t('forum', 'No color selected'),
      },
    }
  },
})
</script>

<style scoped lang="scss">
.color-picker-preset {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.color-picker-label {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--color-main-text);
}

.color-preview {
  width: 20px;
  height: 20px;
  border-radius: 4px;
  border: 1px solid var(--color-border);

  &.empty {
    background: var(--color-background-dark);
  }
}
</style>
