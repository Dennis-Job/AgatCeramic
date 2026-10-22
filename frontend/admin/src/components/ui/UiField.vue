<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'
import { vTooltip } from './tooltip'

defineProps<{
  label: string
  help?: string
  error?: string
  required?: boolean
}>()
</script>
<template>
  <div
    class="ui-field text-sm font-medium text-gray-500"
    :class="{ 'ui-field--help': help }"
  >
    <label class="ui-field-label">
      <span class="ui-field-title"
        >{{ label }}<span v-if="required" aria-hidden="true"> *</span></span
      >
      <div class="ui-field-control"><slot /></div>
      <span
        v-if="error"
        class="ui-field-error mt-1 block text-xs font-normal text-error-500"
        role="alert"
        >{{ error }}</span
      >
    </label>
    <button
      v-if="help"
      v-tooltip.help="help"
      type="button"
      class="ui-field-help admin-focus"
      :aria-label="`Подсказка: ${label}`"
    >
      <CircleAlert class="h-4 w-4" aria-hidden="true" />
    </button>
  </div>
</template>

<style scoped>
.ui-field {
  display: grid;
  min-width: 0;
  grid-template-columns: minmax(0, max-content) minmax(0, 1fr);
  align-content: start;
}
.ui-field--help {
  grid-template-columns:
    minmax(0, max-content)
    minmax(calc(var(--admin-spacing-6) + var(--admin-spacing-1)), 1fr);
}
/* The label keeps its native association with the slotted control; the help
   button is its sibling so clicking help never activates the input. */
.ui-field-label {
  display: contents;
}
.ui-field-title {
  grid-column: 1;
  grid-row: 1;
  min-width: 0;
  align-self: center;
  overflow-wrap: anywhere;
}
.ui-field-control {
  grid-column: 1 / -1;
  grid-row: 2;
  min-width: 0;
}
.ui-field-error {
  grid-column: 1 / -1;
  grid-row: 3;
}
.ui-field-help {
  display: inline-flex;
  grid-column: 2;
  grid-row: 1;
  align-items: center;
  justify-content: center;
  justify-self: start;
  align-self: center;
  width: var(--admin-spacing-6);
  height: var(--admin-spacing-6);
  margin-inline-start: var(--admin-spacing-1);
  border-radius: var(--admin-radius-md);
  color: var(--admin-color-gray-500);
  cursor: help;
}
.ui-field-help:hover {
  background: var(--admin-color-gray-100);
}
</style>
