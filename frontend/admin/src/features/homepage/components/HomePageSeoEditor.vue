<script setup lang="ts">
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import UiImagePreview from '../../../components/ui/UiImagePreview.vue'
import MediaReferenceField from '../../media/components/MediaReferenceField.vue'
import type { HomePageContent } from '../types/homepage.types'

const model = defineModel<HomePageContent['seo']>({ required: true })
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-gray-800">SEO главной страницы</h2>
      <p class="mt-1 text-sm text-gray-500">
        Заголовок, описание и изображение для поисковых систем и социальных
        сетей.
      </p>
    </div>
    <UiField label="Заголовок страницы"
      ><UiInput v-model="model.title"
    /></UiField>
    <UiField label="Описание страницы"
      ><UiTextarea v-model="model.description" rows="3"
    /></UiField>
    <UiField label="Заголовок Open Graph"
      ><UiInput v-model="model.og_title"
    /></UiField>
    <UiField label="Описание Open Graph"
      ><UiTextarea v-model="model.og_description" rows="3"
    /></UiField>
    <MediaReferenceField
      kind="image"
      label="Изображение Open Graph"
      :model-value="model.og_image_media_id"
      @update:model-value="
        model.og_image_media_id = Array.isArray($event) ? null : $event
      "
    />
    <UiField
      label="Локальное изображение"
      help="Путь /images/... используется, пока файл не выбран в медиатеке."
      ><UiInput v-model="model.og_image_url"
    /></UiField>
    <UiImagePreview
      v-if="!model.og_image_media_id"
      :url="model.og_image_url || null"
      alt="Изображение Open Graph"
      class="max-w-sm"
    />
  </div>
</template>
