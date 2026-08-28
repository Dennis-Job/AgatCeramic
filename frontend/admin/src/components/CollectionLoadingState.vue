<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue'

defineProps<{ label: string }>()
</script>

<template>
  <div
    class="flex min-h-36 items-center justify-center gap-3 px-5 py-12 text-sm font-medium text-gray-500"
    role="status"
    aria-live="polite"
  >
    <LoaderCircle class="shrink-0 animate-spin text-primary-500" :size="20" aria-hidden="true" />
    <span>{{ label }}</span>
  </div>
</template>
