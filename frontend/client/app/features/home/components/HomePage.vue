<script setup lang="ts">
import { homeSlides, materialCategories } from '../data/homeContent'
import HomeHero from './HomeHero.vue'
import HomeMarquee from './HomeMarquee.vue'
import HomeCategories from './HomeCategories.vue'
import HomeMaterialShowcase from './HomeMaterialShowcase.vue'
import HomePromo from './HomePromo.vue'
import HomeAbout from './HomeAbout.vue'
import HomeGuide from './HomeGuide.vue'

const selectedMaterialId = ref(materialCategories[0]?.id ?? '')

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
  <HomeHero :slides="homeSlides" />
  <HomeMarquee />
  <HomeCategories :categories="materialCategories" @select="showMaterial" />
  <HomeMaterialShowcase
    :categories="materialCategories"
    :selected-id="selectedMaterialId"
    @select="selectedMaterialId = $event"
  />
  <HomePromo />
  <HomeAbout />
  <HomeGuide />
</template>
