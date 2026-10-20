<script setup lang="ts">
import { X } from '@lucide/vue'

withDefaults(defineProps<{ label?: string; disabled?: boolean }>(), {
  label: 'Закрыть',
})
defineEmits<{ click: [event: MouseEvent] }>()
</script>

<template>
  <button
    type="button"
    class="ui-close-button"
    :aria-label="label"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <X :size="16" :stroke-width="1.6" aria-hidden="true" />
  </button>
</template>

<style scoped>
.ui-close-button {
  display: grid;
  place-items: center;
  flex: 0 0 44px;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid var(--color-line);
  border-radius: 50%;
  background: transparent;
  color: var(--color-ink);
  transition:
    background-color 0.3s,
    border-color 0.3s,
    color 0.3s;
}
.ui-close-button:hover:not(:disabled) {
  border-color: var(--color-ink);
  background: var(--color-ink);
  color: var(--color-white);
}
.ui-close-button:disabled {
  opacity: 0.55;
}
</style>
