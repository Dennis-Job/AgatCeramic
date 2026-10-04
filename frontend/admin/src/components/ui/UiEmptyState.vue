<script setup lang="ts">
import { Inbox } from '@lucide/vue'

defineProps<{ label: string }>()
</script>
<template>
  <div
    class="flex min-h-36 flex-col items-center justify-center gap-2 rounded-2xl bg-blue-light-50 px-5 py-12 text-center text-sm text-gray-900"
    role="status"
    aria-live="polite"
  >
    <Inbox :size="28" class="text-blue-light-700" aria-hidden="true" />
    <p class="font-semibold">{{ label }}</p>
    <slot />
  </div>
</template>
