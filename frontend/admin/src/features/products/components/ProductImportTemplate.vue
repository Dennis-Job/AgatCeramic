<script setup lang="ts">
import { Download } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'

defineProps<{
  visible: boolean
  importMode: 'template' | 'edit'
  categoryId: string
  categoryOptions: { value: string; label: string }[]
  categoriesLoading: boolean
  categoryError: string
  disabled: boolean
  downloading: boolean
}>()
const emit = defineEmits<{
  'update:categoryId': [value: string]
  retryCategories: []
  downloadTemplate: []
}>()
</script>

<template>
  <UiCard data-product-import-step="2">
    <template #header>
      <h2 class="text-base font-semibold text-gray-500">
        2. Подготовьте шаблон
      </h2>
    </template>
    <p class="mt-1 text-sm text-gray-500">
      {{
        importMode === 'template'
          ? 'До 5 000 товаров в одном файле. Шаблон содержит характеристики выбранной категории.'
          : 'Файл содержит все товары выбранной категории, их SKU и характеристики. SKU определяет редактируемый товар.'
      }}
    </p>
    <UiLoadingState
      v-if="categoriesLoading"
      class="mt-3"
      label="Загружаем категории…"
    />
    <UiNotification v-else-if="visible && categoryError"
      >{{ categoryError }}
      <UiButton
        type="button"
        variant="danger-ghost"
        size="sm"
        @click="emit('retryCategories')"
        >Повторить</UiButton
      ></UiNotification
    >
    <UiEmptyState
      v-else-if="!categoryOptions.length"
      class="mt-3"
      label="Категорий пока нет. Сначала создайте категорию в каталоге."
    />
    <fieldset
      v-else
      :disabled="disabled || downloading"
      class="mt-4 grid min-w-0 gap-3 sm:grid-cols-2"
    >
      <UiField class="min-w-0" label="Категория товаров"
        ><UiSelect
          :model-value="categoryId"
          class="mt-1.5"
          :options="categoryOptions"
          :disabled="!visible || disabled || downloading"
          accessible-name="Категория товаров для загрузки"
          placeholder="Выберите категорию"
          searchable
          teleport-menu
          @update:model-value="emit('update:categoryId', $event)"
      /></UiField>
      <UiButton
        type="button"
        variant="secondary"
        class="self-end"
        :loading="downloading"
        :disabled="!categoryId || downloading"
        @click="emit('downloadTemplate')"
        ><Download :size="18" class="shrink-0" aria-hidden="true" />{{
          downloading ? 'Скачиваем…' : 'Скачать шаблон Excel'
        }}</UiButton
      >
    </fieldset>
    <UiAlert
      data-product-import-info
      tone="additional"
      live="polite"
      class="mt-3 leading-6"
    >
      {{
        importMode === 'template'
          ? 'SKU присваивается автоматически. Slug можно оставить пустым — он создастся из наименования.'
          : 'Не изменяйте SKU и не добавляйте строки: редактируются только товары выбранной категории.'
      }}
      Значения списков выбирайте в ячейках Excel; новые значения добавляются на
      сайте с соответствующими правами.
    </UiAlert>
  </UiCard>
</template>
