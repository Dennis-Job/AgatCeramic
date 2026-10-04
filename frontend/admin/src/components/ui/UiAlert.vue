<script setup lang="ts">
withDefaults(
  defineProps<{
    tone?: 'error' | 'success' | 'warning' | 'info'
    live?: 'assertive' | 'polite'
  }>(),
  { tone: 'error', live: 'assertive' },
)
</script>
<template>
  <p
    class="rounded-lg px-4 py-3 text-sm"
    :class="
      tone === 'error'
        ? 'bg-error-50 text-error-500'
        : tone === 'success'
          ? 'bg-success-50 text-success-500'
          : tone === 'warning'
            ? 'bg-warning-50 text-warning-500'
            : 'bg-primary-50 text-primary-500'
    "
    :role="tone === 'error' ? 'alert' : 'status'"
    :aria-live="live"
  >
    <slot />
  </p>
</template>
