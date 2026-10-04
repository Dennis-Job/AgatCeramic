<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    description: string
    headingTag?: 'h1' | 'h2'
  }>(),
  { headingTag: 'h1' },
)
defineEmits<{ submit: [] }>()
</script>

<template>
  <form
    class="admin-panel admin-panel--white admin-panel--elevated w-full max-w-md p-8"
    @submit.prevent="$emit('submit')"
  >
    <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
    <component :is="headingTag" class="mt-3 text-2xl font-bold text-gray-500">{{
      title
    }}</component>
    <p class="mt-2 text-sm text-gray-500">{{ description }}</p>
    <slot />
  </form>
</template>
