<script setup lang="ts">
import { ref } from 'vue'
import UiButton from '../components/ui/UiButton.vue'
import BannersWorkspace from '../features/banners/components/BannersWorkspace.vue'
import PagesWorkspace from '../features/pages/components/PagesWorkspace.vue'
import SlidersWorkspace from '../features/sliders/components/SlidersWorkspace.vue'

const section = ref<'pages' | 'banners' | 'sliders'>('pages')
</script>

<template>
  <div class="mx-auto admin-page">
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Разделы контента">
      <UiButton
        :aria-pressed="section === 'pages'"
        :variant="section === 'pages' ? 'primary' : 'secondary'"
        @click="section = 'pages'"
        >Страницы</UiButton
      >
      <UiButton
        :aria-pressed="section === 'banners'"
        :variant="section === 'banners' ? 'primary' : 'secondary'"
        @click="section = 'banners'"
        >Баннеры</UiButton
      >
      <UiButton
        :aria-pressed="section === 'sliders'"
        :variant="section === 'sliders' ? 'primary' : 'secondary'"
        @click="section = 'sliders'"
        >Слайдеры</UiButton
      >
    </nav>
    <div
      role="region"
      :aria-label="
        section === 'pages'
          ? 'Страницы'
          : section === 'banners'
            ? 'Баннеры'
            : 'Слайдеры'
      "
    >
      <PagesWorkspace v-if="section === 'pages'" />
      <BannersWorkspace v-else-if="section === 'banners'" />
      <SlidersWorkspace v-else />
    </div>
  </div>
</template>
