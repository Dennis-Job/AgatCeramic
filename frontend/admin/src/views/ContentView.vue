<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminWorkspace from '../components/shared/AdminWorkspace.vue'
import UiButton from '../components/ui/UiButton.vue'
import AppearanceWorkspace from '../features/appearance/components/AppearanceWorkspace.vue'
import PagesWorkspace from '../features/pages/components/PagesWorkspace.vue'
import StoresWorkspace from '../features/stores/components/StoresWorkspace.vue'

type Section = 'pages' | 'appearance' | 'stores'
const route = useRoute()
const router = useRouter()
const section = computed<Section>({
  get: () => {
    const value = route.query.section
    return value === 'appearance' || value === 'stores' ? value : 'pages'
  },
  set: (value) => {
    void router.replace({ query: { ...route.query, section: value } })
  },
})
watch(
  () => route.query.section,
  (value) => {
    if (value === 'banners' || value === 'sliders')
      void router.replace({ query: { ...route.query, section: 'pages' } })
  },
  { immediate: true },
)
</script>

<template>
  <AdminWorkspace :mode="section === 'stores' ? 'overview' : 'editor'">
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
            : 'Магазины'
      "
    >
      <PagesWorkspace v-if="section === 'pages'" />
      <AppearanceWorkspace v-else-if="section === 'appearance'" />
      <StoresWorkspace v-else />
    </div>
  </AdminWorkspace>
</template>
