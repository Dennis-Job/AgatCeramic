<script setup lang="ts">
import UiAlert from './UiAlert.vue'

defineProps<{ flush?: boolean }>()
</script>

<template>
  <footer
    class="sticky bottom-0 shrink-0 border-t border-gray-200 bg-white px-5 py-4 sm:px-6"
    :class="flush ? '' : 'mt-6 -mx-6 -mb-6'"
  >
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-end">
      <UiAlert v-if="$slots.note" class="sm:mr-auto" tone="info" live="polite">
        <slot name="note" />
      </UiAlert>
      <div class="flex flex-wrap justify-end gap-3">
        <slot />
      </div>
    </div>
  </footer>
</template>
