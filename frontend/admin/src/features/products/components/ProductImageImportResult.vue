<script setup lang="ts">
import { computed } from 'vue'
import { Download } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import ProductImportStatus from './ProductImportStatus.vue'
import type { ProductImageImport } from '../types/product.types'

const props = defineProps<{
  visible: boolean
  uploading: boolean
  busy: boolean
  downloading: boolean
  progress: number | undefined
  pollingError: boolean
  result: ProductImageImport | null
}>()
const emit = defineEmits<{
  retry: [id: number]
  downloadErrors: []
}>()

const statusText = computed(() => {
  if (props.uploading) return 'Загружаем ZIP-архив…'
  if (props.result?.status === 'pending') return 'Архив ожидает обработки…'
  if (props.result?.status === 'processing')
    return props.result.total_folders
      ? `Обработано ${props.result.processed_folders} из ${props.result.total_folders} папок`
      : 'Проверяем архив и импортируем изображения…'
  return 'Выберите подготовленный ZIP-архив, чтобы начать импорт.'
})
</script>

<template>
  <ProductImportStatus
    title-id="image-import-result-title"
    :visible="visible"
    :job-status="result?.status"
    :failure-count="result?.failed_folders"
    :status-text="statusText"
    :progress="progress"
    :progress-label="
      uploading ? 'Загрузка ZIP-архива' : 'Обработка изображений'
    "
    :busy="busy"
    :polling-error="Boolean(result && pollingError)"
    @retry="result && emit('retry', result.id)"
  >
    <template v-if="result" #summary>
      <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div>
          <dt class="text-gray-500">Папок обработано</dt>
          <dd class="mt-1 font-semibold text-gray-500">
            {{ result.processed_folders }} из
            {{ result.total_folders }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">Добавлено фото</dt>
          <dd class="mt-1 font-semibold text-gray-500">
            {{ result.created_images }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">Заменено фото</dt>
          <dd class="mt-1 font-semibold text-gray-500">
            {{ result.replaced_images }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">Папок с ошибками</dt>
          <dd class="mt-1 font-semibold text-gray-500">
            {{ result.failed_folders }}
          </dd>
        </div>
      </dl>
      <p
        v-if="result.error_message"
        class="mt-3 break-words text-sm text-error-500"
      >
        {{ result.error_message }}
      </p>
    </template>
    <template v-if="result" #details>
      <ul
        v-if="result.errors?.length"
        class="mt-4 max-h-64 space-y-3 overflow-y-auto"
        aria-label="Ошибки импорта изображений"
        tabindex="0"
      >
        <li
          v-for="entry in result.errors"
          :key="`${entry.sku}-${entry.entry}`"
          class="break-words rounded-lg border border-warning-200 bg-white p-3 text-sm"
        >
          <p class="font-semibold text-gray-500">
            {{ entry.sku || 'Без названия папки'
            }}<span v-if="entry.entry" class="font-normal text-gray-500">
              · {{ entry.entry }}</span
            >
          </p>
          <p
            v-for="message in entry.messages"
            :key="message"
            class="mt-1 text-gray-500"
          >
            {{ message }}
          </p>
        </li>
      </ul>
      <UiButton
        v-if="result.has_error_file"
        type="button"
        variant="secondary"
        class="mt-4"
        :loading="downloading"
        :disabled="downloading"
        @click="emit('downloadErrors')"
        ><Download :size="18" class="shrink-0" aria-hidden="true" />{{
          downloading ? 'Скачиваем…' : 'Скачать отчёт с ошибками'
        }}</UiButton
      >
    </template>
  </ProductImportStatus>
</template>
