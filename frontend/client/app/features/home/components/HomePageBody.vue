<script setup lang="ts">
import type { ContentPage } from '~/types/contentPage'
import PageBlocks from '~/features/content/components/PageBlocks.vue'

defineProps<{ content: Pick<ContentPage, 'blocks' | 'seo'> }>()
</script>

<template>
  <div
    v-if="
      !content.blocks.some((block) => block.enabled && block.type === 'hero')
    "
    class="container home-unavailable"
  >
    <h1>{{ content.seo.title || 'AgatCeramic' }}</h1>
    <p v-if="!content.blocks.some((block) => block.enabled)" role="status">
      Для главной страницы пока нет опубликованного содержимого.
    </p>
  </div>
  <PageBlocks
    :blocks="content.blocks"
    :title="content.seo.title"
    :description="content.seo.description"
  />
</template>

<style scoped>
.home-unavailable {
  min-height: 60vh;
  padding-block: 100px;
}
h1 {
  margin: 0 0 16px;
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 500;
}
p {
  color: var(--color-muted);
  line-height: 1.7;
}
</style>
