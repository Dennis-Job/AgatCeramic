<script setup lang="ts">
import type { ContentPage } from '~/types/contentPage'
import PageBlocks from './PageBlocks.vue'
import PageState from '~/components/shared/PageState.vue'

const props = defineProps<{ content: ContentPage }>()
const hasHero = computed(() =>
  props.content.blocks.some((block) => block.enabled && block.type === 'hero'),
)
</script>

<template>
  <div v-if="!hasHero" class="container page-intro">
    <NuxtLink to="/" class="eyebrow">AgatCeramic</NuxtLink>
    <h1>{{ content.title }}</h1>
    <p v-if="content.seo.description">{{ content.seo.description }}</p>
  </div>
  <PageBlocks
    :blocks="content.blocks"
    :title="content.title"
    :description="content.seo.description"
  />
  <div v-if="!content.blocks.some((block) => block.enabled)" class="container">
    <PageState
      title="Содержимое пока не добавлено"
      message="Информация появится после публикации."
    />
  </div>
</template>

<style scoped>
.page-intro {
  padding-block: clamp(60px, 8vw, 110px);
  overflow-wrap: anywhere;
}
h1 {
  max-width: 880px;
  margin: 18px 0 24px;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  line-height: 1.06;
  letter-spacing: -0.02em;
}
p {
  max-width: 680px;
  margin: 0;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.8;
}
</style>
