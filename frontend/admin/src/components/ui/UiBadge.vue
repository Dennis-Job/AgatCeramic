<script setup lang="ts">
withDefaults(
  defineProps<{
    tone?:
      'neutral' | 'primary' | 'additional' | 'success' | 'warning' | 'danger'
  }>(),
  { tone: 'neutral' },
)
</script>
<template>
  <span
    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
    :class="
      tone === 'primary'
        ? 'bg-primary-50 text-primary-500'
        : tone === 'additional'
          ? 'bg-blue-light-50 text-blue-light-700'
          : tone === 'success'
            ? 'bg-success-50 text-success-700'
            : tone === 'warning'
              ? 'bg-warning-50 text-warning-700'
              : tone === 'danger'
                ? 'bg-error-50 text-error-700'
                : 'bg-gray-100 text-gray-600'
    "
    ><slot
  /></span>
</template>
