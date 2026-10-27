<script setup lang="ts">
import { Image, Search, Share2 } from '@lucide/vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import MediaImageEditor from '../../media/components/MediaImageEditor.vue'
import type { HomePageContent } from '../types/homepage.types'

const model = defineModel<HomePageContent['seo']>({ required: true })
defineProps<{ disabled?: boolean }>()
const emit = defineEmits<{
  pending: [value: boolean]
  uploading: [value: boolean]
}>()
</script>

<template>
  <div class="grid min-w-0 gap-3 lg:grid-cols-2">
    <UiCard aria-label="Метаданные для поисковых систем">
      <template #header>
        <h2 class="flex items-center gap-3 font-semibold text-gray-500">
          <Search :size="20" class="shrink-0" aria-hidden="true" />SEO страницы
        </h2>
      </template>
      <div class="space-y-3">
        <UiField label="Заголовок страницы"
          ><UiInput v-model="model.title"
        /></UiField>
        <UiField
          label="Описание страницы"
          :help="
            !model.description.trim()
              ? 'Рекомендуем добавить описание для поисковых систем. Это необязательное поле.'
              : undefined
          "
          ><UiTextarea v-model="model.description" compact rows="2"
        /></UiField>
      </div>
    </UiCard>
    <UiCard aria-label="Метаданные для социальных сетей">
      <template #header>
        <h2 class="flex items-center gap-3 font-semibold text-gray-500">
          <Share2 :size="20" class="shrink-0" aria-hidden="true" />Социальные
          сети
        </h2>
      </template>
      <div class="space-y-3">
        <UiField label="Заголовок Open Graph"
          ><UiInput v-model="model.og_title"
        /></UiField>
        <UiField
          label="Описание Open Graph"
          :help="
            !model.og_description.trim()
              ? 'Рекомендуем добавить описание для социальных сетей. Это необязательное поле.'
              : undefined
          "
          ><UiTextarea v-model="model.og_description" compact rows="2"
        /></UiField>
      </div>
    </UiCard>
    <UiCard class="lg:col-span-2" aria-label="Изображение для социальных сетей">
      <template #header>
        <h2 class="flex items-center gap-3 font-semibold text-gray-500">
          <Image :size="20" class="shrink-0" aria-hidden="true" />Изображение
          Open Graph
        </h2>
      </template>
      <div class="space-y-3">
        <p class="text-xs text-gray-500">
          Это фото увидят при отправке ссылки на страницу в социальных сетях.
        </p>
        <MediaImageEditor
          compact
          :disabled="disabled"
          label="Изображение Open Graph"
          preview-label="Увеличить изображение Open Graph"
          remove-label="Снять выбранный файл Open Graph"
          :fallback-url="model.og_image_url"
          fallback-alt="Изображение Open Graph"
          require-manage-permission
          :model-value="model.og_image_media_id"
          @update:model-value="model.og_image_media_id = $event"
          @pending="emit('pending', $event)"
          @uploading="emit('uploading', $event)"
        >
          <template #details>
            <details class="text-sm text-gray-500">
              <summary class="cursor-pointer rounded admin-focus">
                Исходный адрес изображения
              </summary>
              <UiField
                class="mt-2"
                label="Локальное изображение"
                help="Путь /images/... используется, пока файл не выбран в медиатеке."
                ><UiInput
                  :model-value="model.og_image_url ?? ''"
                  :disabled="disabled"
                  @update:model-value="model.og_image_url = $event"
              /></UiField>
            </details>
          </template>
        </MediaImageEditor>
      </div>
    </UiCard>
  </div>
</template>
