<script setup lang="ts">
import { Download } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import ProductImportStatus from './ProductImportStatus.vue'
import type { ProductImport } from '../types/product.types'

defineProps<{
  visible: boolean
  uploading: boolean
  busy: boolean
  downloading: boolean
  progress: number | undefined
  pollingError: boolean
  result: ProductImport | null
  statusText: string
  successful: number
}>()
const emit = defineEmits<{
  retry: [id: number]
  downloadErrors: []
}>()
</script>

<template>
  <ProductImportStatus
    title-id="product-import-result-title"
    :visible="visible"
    :job-status="result?.status"
    :failure-count="result?.failed_rows"
    :status-text="statusText"
    :progress="progress"
    :progress-label="uploading ? 'Загрузка XLSX-файла' : 'Обработка товаров'"
    :busy="busy"
    :polling-error="Boolean(result && pollingError)"
    @retry="result && emit('retry', result.id)"
  >
    <template v-if="result" #summary>
      <p class="mt-2 text-sm text-gray-500">
        Успешно: {{ successful }}. С ошибками: {{ result.failed_rows ?? 0 }}.
      </p>
      <p
        v-if="result.error_message"
        class="mt-2 break-words text-sm text-error-500"
      >
        {{ result.error_message }}
      </p>
    </template>
    <template v-if="result" #details>
      <ul
        v-if="result.row_errors?.length"
        class="mt-4 max-h-64 space-y-3 overflow-y-auto"
        aria-label="Ошибки товаров"
        tabindex="0"
      >
        <li
          v-for="entry in result.row_errors"
          :key="entry.row"
          class="break-words rounded-lg border border-warning-200 bg-white p-3 text-sm"
        >
          <p class="font-semibold text-gray-500">
            {{ entry.name || 'Без наименования' }}
            <span class="font-normal text-gray-500"
              >· строка {{ entry.row }}</span
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
        ><Download :size="18" class="shrink-0" aria-hidden="true" />Скачать
        Excel с ошибками</UiButton
      >
    </template>
  </ProductImportStatus>
</template>
