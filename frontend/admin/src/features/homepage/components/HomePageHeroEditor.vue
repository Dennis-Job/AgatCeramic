<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { loadAllPages } from '../../../services/pagination'
import { getSliders } from '../../sliders/services/sliders'
import type { Slider } from '../../sliders/types/slider.types'
import type { HomePageContent } from '../types/homepage.types'

const model = defineModel<number | null>({ required: true })
defineProps<{ slides: HomePageContent['hero_slides'] }>()
const sliders = ref<Slider[]>([])
const loading = ref(false)
const error = ref('')

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    sliders.value = await loadAllPages(getSliders)
  } catch (reason) {
    error.value =
      reason instanceof Error
        ? reason.message
        : 'Не удалось загрузить слайдеры.'
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div>
      <h2 class="text-lg font-semibold text-gray-800">Главный слайдер</h2>
      <p class="mt-1 text-sm text-gray-500">
        Создайте баннеры, соберите их в слайдер и опубликуйте оба вида записей.
        Затем выберите этот слайдер здесь.
      </p>
    </div>
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <UiButton v-if="error" variant="secondary" size="sm" @click="load"
      >Повторить загрузку</UiButton
    >
    <div>
      <p class="mb-2 text-sm font-medium text-gray-700">Слайдер для главной</p>
      <UiSelect
        :model-value="model === null ? '' : String(model)"
        :options="[
          { label: 'Не показывать слайдер', value: '' },
          ...sliders.map((item) => ({
            label: `${item.name} (${item.slug})${item.is_published ? '' : ' — черновик'}`,
            value: String(item.id),
          })),
        ]"
        accessible-name="Слайдер для главной"
        searchable
        teleport-menu
        :disabled="loading"
        @update:model-value="model = $event ? Number($event) : null"
      />
    </div>
    <div class="flex flex-wrap gap-3">
      <RouterLink
        to="/content?section=banners"
        class="text-sm font-medium text-primary-700 underline admin-focus"
        >Управлять баннерами</RouterLink
      >
      <RouterLink
        to="/content?section=sliders"
        class="text-sm font-medium text-primary-700 underline admin-focus"
        >Управлять слайдерами</RouterLink
      >
      <UiButton variant="secondary" size="sm" @click="load"
        >Обновить список</UiButton
      >
    </div>
    <div v-if="slides.length" class="space-y-2">
      <h3 class="font-medium text-gray-800">Опубликованные слайды сейчас</h3>
      <ol class="list-inside list-decimal text-sm text-gray-600">
        <li v-for="slide in slides" :key="slide.id">{{ slide.title }}</li>
      </ol>
    </div>
    <p v-else class="text-sm text-gray-500" role="status">
      Пока нет опубликованных слайдов. После сохранения выбора список обновится.
    </p>
  </div>
</template>
