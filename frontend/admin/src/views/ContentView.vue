<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminWorkspace from '../components/shared/AdminWorkspace.vue'
import UiButton from '../components/ui/UiButton.vue'
import AppearanceWorkspace from '../features/appearance/components/AppearanceWorkspace.vue'
import PagesWorkspace from '../features/pages/components/PagesWorkspace.vue'
import StoresWorkspace from '../features/stores/components/StoresWorkspace.vue'

type Section = 'pages' | 'appearance' | 'stores'
const route = useRoute()
const router = useRouter()
const navigation = ref<HTMLElement | null>(null)
const section = computed<Section>({
  get: () => {
    const value = route.query.section
    return value === 'appearance' || value === 'stores' ? value : 'pages'
  },
  set: async (value) => {
    const restoreFocus = navigation.value?.contains(document.activeElement)
    const failure = await router.replace({
      query: { ...route.query, section: value },
    })
    if (failure || !restoreFocus) return
    await nextTick()
    navigation.value
      ?.querySelector<HTMLButtonElement>(
        `button[data-content-section="${value}"]`,
      )
      ?.focus()
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
      <component
        :is="
          section === 'pages'
            ? PagesWorkspace
            : section === 'appearance'
              ? AppearanceWorkspace
              : StoresWorkspace
        "
      >
        <template #toolbar-navigation>
          <nav
            ref="navigation"
            class="flex flex-wrap gap-2"
            aria-label="Разделы контента"
          >
            <UiButton
              data-content-section="pages"
              :aria-pressed="section === 'pages'"
              :variant="section === 'pages' ? 'primary' : 'secondary'"
              @click="section = 'pages'"
              >Страницы</UiButton
            >
            <UiButton
              data-content-section="appearance"
              :aria-pressed="section === 'appearance'"
              :variant="section === 'appearance' ? 'primary' : 'secondary'"
              @click="section = 'appearance'"
              >Общее оформление</UiButton
            >
            <UiButton
              data-content-section="stores"
              :aria-pressed="section === 'stores'"
              :variant="section === 'stores' ? 'primary' : 'secondary'"
              @click="section = 'stores'"
              >Магазины</UiButton
            >
          </nav>
        </template>
      </component>
    </div>
  </AdminWorkspace>
</template>
