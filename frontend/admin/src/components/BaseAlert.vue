<script setup lang="ts">
withDefaults(defineProps<{
  tone?: 'error' | 'success'
  live?: 'assertive' | 'polite'
}>(), {
  tone: 'error',
  live: 'assertive',
})
</script>

<template>
  <p
    class="rounded-lg border px-4 py-3 text-sm"
    :class="tone === 'error'
      ? 'border-error-200 bg-error-50 text-error-500'
      : 'border-success-200 bg-success-50 text-success-700'"
    :role="tone === 'error' ? 'alert' : 'status'"
    :aria-live="live"
  >
    <slot />
  </p>
</template>
