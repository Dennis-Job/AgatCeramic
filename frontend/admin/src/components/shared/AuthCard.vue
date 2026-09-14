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
    class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-dialog"
    @submit.prevent="$emit('submit')"
  >
    <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
    <component :is="headingTag" class="mt-3 text-2xl font-bold text-gray-900">{{
      title
    }}</component>
    <p class="mt-2 text-sm text-gray-500">{{ description }}</p>
    <slot />
  </form>
</template>
