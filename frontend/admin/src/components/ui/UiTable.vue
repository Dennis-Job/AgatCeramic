<script setup lang="ts">
withDefaults(defineProps<{ minWidth?: string; label?: string; tableClass?: string }>(), { minWidth: undefined, label: undefined, tableClass: undefined })
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm" :class="[minWidth, tableClass]" :aria-label="label"><slot /></table>
  </div>
</template>
