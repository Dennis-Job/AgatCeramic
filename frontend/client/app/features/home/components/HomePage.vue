<script setup lang="ts">
import type { HomePageContent } from '~/types/homePage'
import HomeHero from './HomeHero.vue'
import HomeMarquee from './HomeMarquee.vue'
import HomeCategories from './HomeCategories.vue'
import HomeMaterialShowcase from './HomeMaterialShowcase.vue'
import HomePromo from './HomePromo.vue'
import HomeAbout from './HomeAbout.vue'
import HomeGuide from './HomeGuide.vue'

const props = defineProps<{ content: HomePageContent }>()
const selectedMaterialId = ref(props.content.categories.items[0]?.id ?? '')

watch(
  () => props.content.categories.items,
  (categories) => {
    if (
      !categories.some((category) => category.id === selectedMaterialId.value)
    )
      selectedMaterialId.value = categories[0]?.id ?? ''
  },
)

async function showMaterial(id: string) {
  selectedMaterialId.value = id
  await nextTick()
  document.getElementById(`tab-${id}`)?.focus({ preventScroll: true })
  const reducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)',
  ).matches
  document
    .getElementById('materials')
    ?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth' })
}
</script>

<template>
  <HomeHero
    :slides="content.heroSlides"
    :empty-title="content.seo.title"
    :empty-description="content.seo.description"
  />
  <HomeMarquee
    v-if="content.marqueeTopics.length"
    :topics="content.marqueeTopics"
  />
  <HomeCategories
    v-if="content.categories.items.length"
    :content="content.categories"
    @select="showMaterial"
  />
  <HomeMaterialShowcase
    v-if="content.categories.items.length"
    :categories="content.categories.items"
    :content="content.materials"
    :selected-id="selectedMaterialId"
    @select="selectedMaterialId = $event"
  />
  <HomePromo :content="content.promo" />
  <HomeAbout :content="content.about" />
  <HomeGuide v-if="content.guide.items.length" :content="content.guide" />
</template>
