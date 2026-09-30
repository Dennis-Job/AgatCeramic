<script setup lang="ts">
import type { HomePageContent } from '~/types/homePage'
import PageBlocks from '~/features/content/components/PageBlocks.vue'

defineProps<{ content: HomePageContent }>()
</script>

<template>
  <PageBlocks
    :blocks="content.blocks"
    :title="content.seo.title"
    :description="content.seo.description"
  />
</template>
