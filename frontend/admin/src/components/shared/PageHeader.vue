<script setup lang="ts">
defineProps<{ title: string; description?: string; eyebrow?: string }>()
</script>

<template>
  <header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
      <p v-if="eyebrow" class="text-sm font-medium text-gray-500">{{ eyebrow }}</p>
      <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ title }}</h1>
      <p v-if="description" class="mt-1 max-w-3xl text-sm text-gray-500">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex max-w-full flex-wrap items-center gap-2"><slot name="actions" /></div>
  </header>
</template>
