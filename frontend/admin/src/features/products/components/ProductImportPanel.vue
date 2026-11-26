<script setup lang="ts">
import UiCard from '../../../components/ui/UiCard.vue'
import UiRadio from '../../../components/ui/UiRadio.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import type { ProductImport } from '../types/product.types'
import ProductImportTemplate from './ProductImportTemplate.vue'
import ProductImportUpload from './ProductImportUpload.vue'
import ProductImportResult from './ProductImportResult.vue'

defineProps<{
  visible: boolean
  file: File | null
  busy: boolean
  downloading: boolean
  uploading: boolean
  error: string
  pollingError: boolean
  progress: number | undefined
  importMode: 'template' | 'edit'
  categoryId: string
  categoryOptions: { value: string; label: string }[]
  categoriesLoading: boolean
  categoryError: string
  notice: string
  result: ProductImport | null
  statusText: string
  successful: number
}>()
const emit = defineEmits<{
  select: [file: File | null]
  upload: []
  retry: [id: number]
  downloadErrors: []
  'update:importMode': [value: 'template' | 'edit']
  'update:categoryId': [value: string]
  retryCategories: []
  downloadTemplate: []
}>()
</script>

<template>
  <div class="space-y-5 py-5 sm:py-6">
    <UiCard data-product-import-step="1">
      <template #header>
        <h2
          id="product-import-step-1-title"
          class="text-base font-semibold text-gray-500"
        >
          1. Выберите сценарий
        </h2>
      </template>
      <fieldset
        :disabled="busy || downloading"
        aria-labelledby="product-import-step-1-title"
        class="grid gap-3 sm:grid-cols-2"
      >
        <UiRadio
          :model-value="importMode"
          name="product-import-mode"
          value="template"
          @update:model-value="emit('update:importMode', 'template')"
          ><span
            ><span class="block font-semibold text-gray-500"
              >Добавить товары</span
            ><span class="mt-1 block text-gray-500"
              >Создайте товары по шаблону выбранной категории.</span
            ></span
          ></UiRadio
        >
        <UiRadio
          :model-value="importMode"
          name="product-import-mode"
          value="edit"
          @update:model-value="emit('update:importMode', 'edit')"
          ><span
            ><span class="block font-semibold text-gray-500"
              >Редактировать товары</span
            ><span class="mt-1 block text-gray-500"
              >Скачайте товары категории с SKU и списками характеристик.</span
            ></span
          ></UiRadio
        >
      </fieldset>
    </UiCard>
    <div data-product-import-steps-pair class="grid gap-5 lg:grid-cols-2">
      <ProductImportTemplate
        :visible="visible"
        :import-mode="importMode"
        :category-id="categoryId"
        :category-options="categoryOptions"
        :categories-loading="categoriesLoading"
        :category-error="categoryError"
        :disabled="busy"
        :downloading="downloading"
        @update:category-id="emit('update:categoryId', $event)"
        @retry-categories="emit('retryCategories')"
        @download-template="emit('downloadTemplate')"
      />
      <ProductImportUpload
        data-product-import-step="3"
        format="xlsx"
        :file="file"
        :busy="busy"
        :disabled="!categoryId"
        @select="emit('select', $event)"
        @upload="emit('upload')"
      />
    </div>
    <UiNotification v-if="visible && error">{{ error }}</UiNotification>
    <UiNotification v-if="visible && notice" tone="success" live="polite">{{
      notice
    }}</UiNotification>
    <ProductImportResult
      :visible="visible"
      :result="result"
      :uploading="uploading"
      :busy="busy"
      :downloading="downloading"
      :progress="progress"
      :polling-error="pollingError"
      :status-text="statusText"
      :successful="successful"
      @retry="emit('retry', $event)"
      @download-errors="emit('downloadErrors')"
    />
  </div>
</template>
