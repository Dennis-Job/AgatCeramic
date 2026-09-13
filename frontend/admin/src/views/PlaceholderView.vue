<script setup lang="ts">
import PageHeader from '../components/shared/PageHeader.vue'

defineProps<{ title: string }>()
</script>

<template>
  <section>
    <PageHeader
      :title="title"
      description="Раздел будет подключён к API в соответствующей задаче."
    />
  </section>
</template>
