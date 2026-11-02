<script setup lang="ts">
import { CircleAlert } from '@lucide/vue'
import { vTooltip } from './tooltip'

defineProps<{ label: string; help: string }>()
</script>

<template>
  <button
    v-tooltip.help="help"
    type="button"
    class="ui-help-tooltip admin-focus"
    :aria-label="`Подсказка: ${label}`"
  >
    <CircleAlert class="h-4 w-4" aria-hidden="true" />
  </button>
</template>

<style scoped>
.ui-help-tooltip {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: var(--admin-spacing-6);
  height: var(--admin-spacing-6);
  border-radius: var(--admin-radius-md);
  color: var(--admin-color-gray-500);
  cursor: help;
}
.ui-help-tooltip:hover {
  background: var(--admin-color-gray-100);
}
</style>
