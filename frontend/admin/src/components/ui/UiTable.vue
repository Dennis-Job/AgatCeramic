<script setup lang="ts">
withDefaults(defineProps<{ minWidth?: string; label?: string; tableClass?: string }>(), { minWidth: undefined, label: undefined, tableClass: undefined })
</script>

<template>
  <div class="min-w-0 max-w-full overflow-x-auto [contain:paint] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500" role="region" :aria-label="label" :tabindex="label ? 0 : undefined">
    <table class="w-full text-left text-sm" :class="[minWidth, tableClass]" :aria-label="label"><slot /></table>
  </div>
</template>
