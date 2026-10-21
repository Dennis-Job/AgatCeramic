<script setup lang="ts">
defineProps<{
  label: string
  disabled?: boolean
  expanded?: boolean
  controls?: string
  plain?: boolean
}>()
defineEmits<{ click: [event: MouseEvent] }>()
</script>

<template>
  <button
    type="button"
    class="ui-icon-button"
    :class="{ 'ui-icon-button--plain': plain }"
    :aria-label="label"
    :aria-expanded="expanded"
    :aria-controls="controls"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>

<style scoped>
.ui-icon-button {
  display: grid;
  place-items: center;
  flex: 0 0 44px;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--color-ink);
  transition:
    background-color 0.3s,
    opacity 0.3s;
}
.ui-icon-button:hover:not(:disabled):not(.ui-icon-button--plain) {
  background: var(--color-beige);
}
.ui-icon-button--plain:hover:not(:disabled) {
  opacity: 0.65;
}
.ui-icon-button:disabled {
  opacity: 0.55;
}
</style>
