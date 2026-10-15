<script setup lang="ts">
withDefaults(
  defineProps<{
    checked: boolean
    accessibleName: string
    disabled?: boolean
    busy?: boolean
  }>(),
  { disabled: false, busy: false },
)
const emit = defineEmits<{ 'update:checked': [value: boolean] }>()
</script>
<template>
  <button
    type="button"
    role="switch"
    class="ui-switch admin-focus"
    :aria-label="accessibleName"
    :aria-checked="checked"
    :aria-busy="busy || undefined"
    :disabled="disabled || busy"
    @click="emit('update:checked', !checked)"
  >
    <span
      class="ui-switch-track"
      :class="{ 'ui-switch-track--on': checked }"
      aria-hidden="true"
      ><span class="ui-switch-thumb"
    /></span>
  </button>
</template>
<style scoped>
.ui-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: var(--admin-control-height-md);
  min-height: var(--admin-control-height-md);
  border-radius: var(--admin-radius-lg);
  cursor: pointer;
}
.ui-switch:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}
.ui-switch-track {
  display: flex;
  align-items: center;
  width: 38px;
  height: 22px;
  padding: 3px;
  border-radius: 999px;
  background: var(--admin-color-gray-400);
  transition: background-color var(--admin-transition-duration, 150ms);
}
.ui-switch-track--on {
  background: var(--admin-color-primary-500);
}
.ui-switch-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--admin-color-white);
  transition: transform var(--admin-transition-duration, 150ms);
}
.ui-switch-track--on .ui-switch-thumb {
  transform: translateX(16px);
}
@media (pointer: coarse) {
  .ui-switch {
    min-width: 44px;
    min-height: 44px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .ui-switch-track,
  .ui-switch-thumb {
    transition: none;
  }
}
@media (forced-colors: active) {
  .ui-switch-track {
    border: 1px solid ButtonText;
    background: Canvas;
  }
  .ui-switch-track--on {
    background: Highlight;
  }
  .ui-switch-thumb {
    background: ButtonText;
  }
}
</style>
