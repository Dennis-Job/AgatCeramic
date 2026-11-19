<script setup lang="ts">
import { Check } from '@lucide/vue'

defineProps<{
  label: string
  disabled?: boolean
  size?: 'normal' | 'compact'
  variant?: 'switch'
}>()
const model = defineModel<boolean>({ required: true })
</script>

<template>
  <label
    class="ui-checkbox"
    :class="{
      'ui-checkbox--compact': size === 'compact',
      'ui-checkbox--switch': variant === 'switch',
    }"
  >
    <input
      v-model="model"
      type="checkbox"
      :role="variant === 'switch' ? 'switch' : undefined"
      :disabled="disabled"
    />
    <Check
      v-if="size === 'compact' && variant !== 'switch'"
      class="ui-checkbox__mark"
      :size="12"
      :stroke-width="1.5"
      aria-hidden="true"
    />
    <span
      v-if="variant === 'switch'"
      class="ui-checkbox__thumb"
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
  background: var(--field-background, transparent);
}
.ui-checkbox--compact input:checked {
  background: var(--color-ink);
  border-color: var(--color-ink);
}
.ui-checkbox__mark {
  position: absolute;
  left: 2px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-white);
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
.ui-checkbox--switch {
  position: relative;
  min-height: 32px;
}
.ui-checkbox--switch input {
  appearance: none;
  order: 2;
  margin: 0 0 0 auto;
  width: 34px;
  height: 18px;
  border-radius: 18px;
  border: 1px solid var(--color-muted);
  background: var(--field-background, var(--color-bg-soft));
}
.ui-checkbox--switch input:checked {
  background: var(--color-ink);
  border-color: var(--color-ink);
}
.ui-checkbox__thumb {
  position: absolute;
  right: 18px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--color-muted);
  pointer-events: none;
  transition: transform 0.2s;
}
.ui-checkbox--switch input:checked + .ui-checkbox__thumb {
  transform: translateX(16px);
  background: var(--color-white);
}
@media (prefers-reduced-motion: reduce) {
  .ui-checkbox__thumb {
    transition: none;
  }
}
@media (pointer: coarse) {
  .ui-checkbox--compact {
    min-height: 44px;
  }
}
@media (forced-colors: active) {
  .ui-checkbox--compact input,
  .ui-checkbox--switch input {
    appearance: auto;
  }
  .ui-checkbox__mark {
    display: none;
  }
  .ui-checkbox__thumb {
    display: none;
  }
}
</style>
