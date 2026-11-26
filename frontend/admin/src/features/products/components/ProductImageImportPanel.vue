<script setup lang="ts">
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import type { ProductImageImport } from '../types/product.types'
import ProductImageImportResult from './ProductImageImportResult.vue'
import ProductImportUpload from './ProductImportUpload.vue'

defineProps<{
  visible: boolean
  file: File | null
  busy: boolean
  downloading: boolean
  uploading: boolean
  error: string
  pollingError: boolean
  progress: number | undefined
  result: ProductImageImport | null
}>()
const emit = defineEmits<{
  select: [file: File | null]
  upload: []
  retry: [id: number]
  downloadErrors: []
}>()
</script>

<template>
  <div class="space-y-5 py-5 sm:py-6">
    <UiCard data-product-image-import-step="1">
      <template #header>
        <h2
          id="image-import-preparation-title"
          class="text-base font-semibold text-gray-500"
        >
          1. Подготовьте архив
        </h2>
      </template>
      <div class="min-w-0">
        <h3 class="text-sm font-semibold text-gray-500">
          Структура ZIP-архива
        </h3>
        <p class="mt-1 text-sm leading-6 text-gray-500">
          Для каждого уже созданного товара создайте папку с его SKU. Внутри
          назовите файлы по схеме
          <code class="rounded bg-gray-100 px-1 py-0.5 text-gray-500"
            >SKU_номер.расширение</code
          >.
        </p>
      </div>
      <div
        class="mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-gray-25 p-3 text-sm text-gray-500"
        tabindex="0"
        aria-label="Пример структуры ZIP-архива"
      >
        <pre class="min-w-max font-mono leading-6">
images.zip
└── 6000011/
    ├── 6000011_1.jpg
    ├── 6000011_2.webp
    └── 6000011_3.png</pre>
      </div>
      <UiAlert
        data-product-image-import-info
        tone="additional"
        live="polite"
        class="mt-4 leading-6"
      >
        <span class="block"
          >Поддерживаются JPG, PNG и WebP; ZIP — до 500 МБ.</span
        >
        <span class="mt-1 block"
          >Имя папки и начало имени каждого файла должны совпадать со SKU
          товара.</span
        >
        <span class="mt-1 block"
          >Фото с номером
          <code class="rounded bg-gray-100 px-1 py-0.5">_1</code>
          станет обложкой. При том же номере новое фото заменит старое, а
          отсутствующие в архиве фото сохранятся.</span
        >
        <span class="mt-1 block"
          >Обработка выполняется в фоне. Ошибка одной папки не помешает
          импортировать остальные товары.</span
        >
      </UiAlert>
    </UiCard>

    <ProductImportUpload
      data-product-image-import-step="2"
      format="zip"
      :file="file"
      :busy="busy"
      @select="emit('select', $event)"
      @upload="emit('upload')"
    />
    <UiNotification v-if="visible && error">{{ error }}</UiNotification>
    <ProductImageImportResult
      :visible="visible"
      :result="result"
      :uploading="uploading"
      :busy="busy"
      :downloading="downloading"
      :progress="progress"
      :polling-error="pollingError"
      @retry="emit('retry', $event)"
      @download-errors="emit('downloadErrors')"
    />
  </div>
</template>
