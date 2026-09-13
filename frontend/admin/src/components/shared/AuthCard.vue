<script setup lang="ts">
defineProps<{ title: string; description: string }>()
defineEmits<{ submit: [] }>()
</script>

<template>
  <form class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-dialog" @submit.prevent="$emit('submit')">
    <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
    <h1 class="mt-3 text-2xl font-bold text-gray-900">{{ title }}</h1>
    <p class="mt-2 text-sm text-gray-500">{{ description }}</p>
    <slot />
  </form>
</template>
