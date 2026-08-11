<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<template>
  <section>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ title }}</h1>
    <p class="mt-2 text-sm text-slate-500">Раздел будет подключён к API в соответствующей задаче.</p>
  </section>
</template>
