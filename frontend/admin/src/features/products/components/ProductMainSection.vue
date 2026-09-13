<script setup lang="ts">
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { copiedFromName, copiedNameError, form, manuallyEditedSlug, slugify, selectedCategory, selectedBrand, categoryOptions, brandOptions, editing, units, saveMain } = useProductEditorContext()
</script>

<template>
<form id="product-main-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveMain">
  <UiField label="Название" :help="copiedFromName ? `Укажите название, отличающееся от «${copiedFromName}».` : undefined" :error="copiedNameError" required><UiInput :model-value="form.name" data-autofocus class="mt-1.5" required :placeholder="copiedFromName ? 'Введите новое название' : ''" :aria-invalid="copiedNameError ? 'true' : undefined" @update:model-value="v => { form.name = v; copiedNameError = ''; if (!manuallyEditedSlug) form.slug = slugify(v) }" /></UiField>
  <UiField label="URL (slug)" required><UiInput v-model="form.slug" class="mt-1.5" required pattern="[a-z0-9]+(-[a-z0-9]+)*" @update:model-value="manuallyEditedSlug = true" /></UiField>
  <UiField label="Категория"><UiSelect v-model="selectedCategory" class="mt-1.5" :options="categoryOptions" placeholder="Выберите категорию" accessible-name="Категория" searchable /></UiField>
  <UiField label="Бренд"><UiSelect v-model="selectedBrand" class="mt-1.5" :options="brandOptions" accessible-name="Бренд" searchable /></UiField>
  <div class="text-sm font-medium"><span id="product-sku-label">SKU</span><p aria-labelledby="product-sku-label" class="mt-1.5 flex min-h-11 items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-gray-700">{{ editing?.sku ?? 'Будет назначен автоматически после сохранения' }}</p></div>
  <UiField label="Артикул"><UiInput :model-value="form.article_number ?? ''" class="mt-1.5" @update:model-value="v => form.article_number = v || null" /></UiField>
  <UiField label="Штрихкод"><UiInput :model-value="form.barcode ?? ''" class="mt-1.5" inputmode="numeric" pattern="([0-9]{8}|[0-9]{12,14})" @update:model-value="v => form.barcode = v || null" /></UiField>
  <UiField label="Единица продажи"><UiSelect v-model="form.unit" class="mt-1.5" :options="units" accessible-name="Единица продажи" /></UiField>
  <UiField label="Цена" required><UiInput v-model="form.price" class="mt-1.5" type="number" min="0" step="0.01" required /></UiField>
  <UiField label="Старая цена"><UiInput :model-value="form.old_price ?? ''" class="mt-1.5" type="number" min="0" step="0.01" @update:model-value="v => form.old_price = v || null" /></UiField>
  <UiField label="Остаток"><UiInput :model-value="String(form.stock_quantity)" class="mt-1.5" type="number" min="0" @update:model-value="v => form.stock_quantity = Number(v)" /></UiField>
  <UiCheckbox class="self-end" mode="boolean" :checked="form.is_on_sale" accessible-name="Товар участвует в распродаже" @update:checked="form.is_on_sale = $event">Распродажа</UiCheckbox>
  <UiField class="sm:col-span-2" label="Описание"><UiTextarea v-model="form.description" class="mt-1.5" /></UiField>
  <UiAlert class="sm:col-span-2" tone="info" live="polite">Новый товар сохранится как черновик. Публикация доступна на шаге «Проверка».</UiAlert>
</form>
</template>
