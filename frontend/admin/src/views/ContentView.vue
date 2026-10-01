<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '../components/ui/UiButton.vue'
import AppearanceWorkspace from '../features/appearance/components/AppearanceWorkspace.vue'
import BannersWorkspace from '../features/banners/components/BannersWorkspace.vue'
import PagesWorkspace from '../features/pages/components/PagesWorkspace.vue'
import SlidersWorkspace from '../features/sliders/components/SlidersWorkspace.vue'
import StoresWorkspace from '../features/stores/components/StoresWorkspace.vue'

type Section = 'pages' | 'appearance' | 'banners' | 'sliders' | 'stores'
const route = useRoute()
const router = useRouter()
const section = computed<Section>({
  get: () => {
    const value = route.query.section
    return value === 'appearance' ||
      value === 'banners' ||
      value === 'sliders' ||
      value === 'stores'
      ? value
      : 'pages'
  },
  set: (value) => {
    void router.replace({ query: { ...route.query, section: value } })
  },
})
</script>

<template>
  <div class="min-w-0">
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Разделы контента">
      <UiButton
        :aria-pressed="section === 'pages'"
        :variant="section === 'pages' ? 'primary' : 'secondary'"
        @click="section = 'pages'"
        >Страницы</UiButton
      >
      <UiButton
        :aria-pressed="section === 'appearance'"
        :variant="section === 'appearance' ? 'primary' : 'secondary'"
        @click="section = 'appearance'"
        >Общее оформление</UiButton
      >
      <UiButton
        :aria-pressed="section === 'stores'"
        :variant="section === 'stores' ? 'primary' : 'secondary'"
        @click="section = 'stores'"
        >Магазины</UiButton
      >
    </nav>
    <div
      role="region"
      :aria-label="
        section === 'pages'
          ? 'Страницы'
          : section === 'appearance'
            ? 'Общее оформление'
            : section === 'banners'
              ? 'Баннеры'
              : section === 'sliders'
                ? 'Слайдеры'
                : 'Магазины'
      "
    >
      <PagesWorkspace v-if="section === 'pages'" />
      <AppearanceWorkspace v-else-if="section === 'appearance'" />
      <BannersWorkspace v-else-if="section === 'banners'" />
      <SlidersWorkspace v-else-if="section === 'sliders'" />
      <StoresWorkspace v-else />
    </div>
  </div>
</template>
