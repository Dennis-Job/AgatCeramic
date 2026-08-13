<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<template>
  <section>
    <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ title }}</h1>
    <p class="mt-2 text-sm text-gray-500">Раздел будет подключён к API в соответствующей задаче.</p>
  </section>
</template>
