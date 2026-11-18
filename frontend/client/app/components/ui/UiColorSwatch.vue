<script setup lang="ts">
import { hexColor } from '~/utils/hexColor'

const props = defineProps<{
  label: string
  color: string
  disabled?: boolean
}>()
const model = defineModel<boolean>({ required: true })
const swatchColor = computed(() => hexColor(props.color) || 'transparent')
</script>

<template>
  <label class="ui-color-swatch" :title="label">
    <input
      v-model="model"
      type="checkbox"
      :aria-label="label"
      :title="label"
      :disabled="disabled"
    />
    <span
      class="ui-color-swatch__color"
      :style="{ '--swatch-color': swatchColor }"
      aria-hidden="true"
    />
  </label>
</template>

<style scoped>
.ui-color-swatch {
  position: relative;
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  cursor: pointer;
}
input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: inherit;
}
.ui-color-swatch__color {
  width: 24px;
  height: 24px;
  border: 1px solid var(--color-line-strong);
  border-radius: 50%;
  background: var(--swatch-color);
  pointer-events: none;
}
input:checked + .ui-color-swatch__color {
  border: 2px solid var(--color-ink);
  outline: 2px solid var(--color-ink);
  outline-offset: 2px;
}
input:focus-visible + .ui-color-swatch__color {
  outline: 2px dashed var(--color-ink);
  outline-offset: 3px;
}
.ui-color-swatch:has(input:disabled) {
  opacity: 0.55;
  cursor: not-allowed;
}
@media (pointer: coarse) {
  .ui-color-swatch {
    width: 44px;
    height: 44px;
  }
}
@media (forced-colors: active) {
  .ui-color-swatch__color {
    forced-color-adjust: none;
  }
  input:checked + .ui-color-swatch__color,
  input:focus-visible + .ui-color-swatch__color {
    outline-color: CanvasText;
  }
}
</style>
