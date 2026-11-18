<script setup lang="ts">
import { Check } from '@lucide/vue'

defineProps<{
  label: string
  disabled?: boolean
  size?: 'normal' | 'compact'
}>()
const model = defineModel<boolean>({ required: true })
</script>

<template>
  <label
    class="ui-checkbox"
    :class="{ 'ui-checkbox--compact': size === 'compact' }"
  >
    <input v-model="model" type="checkbox" :disabled="disabled" />
    <Check
      v-if="size === 'compact'"
      class="ui-checkbox__mark"
      :size="12"
      :stroke-width="1.5"
      aria-hidden="true"
    />
    <span>{{ label }}</span>
  </label>
</template>

<style scoped>
.ui-checkbox {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 44px;
  font-size: 13px;
  line-height: 1.6;
  overflow-wrap: anywhere;
  cursor: pointer;
}
input {
  flex: none;
  width: 18px;
  height: 18px;
  accent-color: var(--color-ink);
}
input:disabled,
input:disabled + span {
  opacity: 0.55;
  cursor: not-allowed;
}
.ui-checkbox--compact {
  position: relative;
  gap: 8px;
  min-height: 28px;
}
.ui-checkbox--compact input {
  appearance: none;
  width: 16px;
  height: 16px;
  margin: 0;
  border: 1px solid var(--color-line-strong);
  border-radius: 0;
  background: transparent;
}
.ui-checkbox--compact input:checked {
  background: var(--color-bg-soft);
}
.ui-checkbox__mark {
  position: absolute;
  left: 2px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-muted);
  pointer-events: none;
  visibility: hidden;
}
input:checked + .ui-checkbox__mark {
  visibility: visible;
}
.ui-checkbox--compact:has(input:disabled) {
  opacity: 0.55;
  cursor: not-allowed;
}
.ui-checkbox--compact input:disabled {
  opacity: 1;
}
@media (pointer: coarse) {
  .ui-checkbox--compact {
    min-height: 44px;
  }
}
@media (forced-colors: active) {
  .ui-checkbox--compact input {
    appearance: auto;
  }
  .ui-checkbox__mark {
    display: none;
  }
}
</style>
