<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown, Copy, Download, EyeOff, Package, Pencil, Plus, Save, Trash2, Upload, X } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiRadio from '../../../components/ui/UiRadio.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import ProductImportDialog from './ProductImportDialog.vue'
import ProductPriceStatusImportDialog from './ProductPriceStatusImportDialog.vue'
import ProductGroupImportDialog from './ProductGroupImportDialog.vue'
import EditorSteps from './ProductEditorSteps.vue'
import ProductMainSection from './ProductMainSection.vue'
import ProductAttributesSection from './ProductAttributesSection.vue'
import ProductImagesSection from './ProductImagesSection.vue'
import ProductVariantsSection from './ProductVariantsSection.vue'
import ProductRelationsSection from './ProductRelationsSection.vue'
import ProductReviewSection from './ProductReviewSection.vue'
import { provideProductEditorContext } from '../composables/useProductEditorContext'
import { useProductEditor } from '../composables/useProductEditor'

const editor = useProductEditor()
provideProductEditorContext(editor)
const { products, pagination, error, loading, opened, saving, editing, deleting, activeStep, steps, success, confirmError, filterResultStatus, exportStatus, exporting, importOpened, priceStatusImportOpened, groupImportOpened, productCountUnavailable, filters, sort, direction, form, selectedGroupId, groupDeleting, imageDeleting, canManage, canManageImports, sortStatus, activityOptions, saleOptions, filterCategoryOptions, filterBrandOptions, hasActiveFilters, productCountLabel, ariaSort, formatDate, isProductNameTruncated, productNamePreview, load, resetFilters, changeSort, exportFilteredProducts, enabled, open, cloneProduct, close, removeProduct, removeImage, ungroup, publish, hideProduct } = editor
type Step = typeof activeStep.value
</script>

<template>
  <section class="mx-auto" :aria-busy="loading">
    <PageHeader class="mb-7" eyebrow="Каталог" title="Товары">
      <template #actions>
        <UiButton v-if="canManageImports" type="button" variant="secondary" @click="importOpened = true"><Upload :size="18" aria-hidden="true" />Загрузить массово</UiButton>
        <UiButton v-if="canManageImports" type="button" variant="secondary" @click="priceStatusImportOpened = true"><Upload :size="18" aria-hidden="true" />Цены и статусы</UiButton>
        <UiButton v-if="canManageImports && canManage" type="button" variant="secondary" @click="groupImportOpened = true"><Upload :size="18" aria-hidden="true" />Группы вариантов</UiButton>
        <UiButton v-if="canManageImports" type="button" variant="secondary" :loading="exporting" :disabled="exporting" :aria-busy="exporting" @click="exportFilteredProducts"><Download :size="18" aria-hidden="true" />{{ exporting ? 'Экспорт…' : 'Скачать Excel' }}</UiButton>
        <UiButton v-if="canManage" type="button" @click="open()"><Plus :size="18" aria-hidden="true" />Добавить товар</UiButton>
      </template>
    </PageHeader>

    <UiAlert v-if="error && !opened" class="mb-4">{{ error }}</UiAlert>
    <UiAlert v-if="exportStatus" class="mb-4" tone="success" live="polite">{{ exportStatus }}</UiAlert>
    <ProductImportDialog :open="importOpened" @close="importOpened = false" @completed="load(1)" />
    <ProductPriceStatusImportDialog :open="priceStatusImportOpened" @close="priceStatusImportOpened = false" @completed="load(1)" />
    <ProductGroupImportDialog :open="groupImportOpened" @close="groupImportOpened = false" @completed="load(1)" />

    <form class="rounded-xl border border-gray-200 bg-white p-5 shadow-card" role="search" @submit.prevent>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <UiField class="xl:col-span-2" label="Поиск"><UiInput v-model="filters.search" class="mt-1.5" searchable placeholder="Название, SKU, артикул" /></UiField>
        <UiField label="Категория"><UiSelect v-model="filters.category_id" class="mt-1.5" :options="filterCategoryOptions" accessible-name="Категория" search-placeholder="Начните вводить название категории" searchable /></UiField>
        <UiField label="Бренд"><UiSelect v-model="filters.brand_id" class="mt-1.5" :options="filterBrandOptions" accessible-name="Бренд" search-placeholder="Начните вводить название бренда" searchable /></UiField>
      </div>
      <div class="mt-5 grid gap-4 xl:grid-cols-2">
        <fieldset><legend class="text-sm font-semibold text-gray-700">Активность</legend><div class="mt-2 grid gap-2 sm:grid-cols-3"><UiRadio v-for="option in activityOptions" :key="option.value" v-model="filters.is_active" :value="option.value" name="product-activity-filter">{{ option.label }}</UiRadio></div></fieldset>
        <fieldset><legend class="text-sm font-semibold text-gray-700">Распродажа</legend><div class="mt-2 grid gap-2 sm:grid-cols-3"><UiRadio v-for="option in saleOptions" :key="option.value" v-model="filters.is_on_sale" :value="option.value" name="product-sale-filter">{{ option.label }}</UiRadio></div></fieldset>
      </div>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-2"><UiBadge data-testid="product-count" :tone="productCountUnavailable ? 'danger' : loading ? 'neutral' : 'primary'">{{ productCountLabel }}</UiBadge><UiButton type="button" variant="ghost" :disabled="!hasActiveFilters" @click="resetFilters">Сбросить</UiButton></div>
    </form>
    <p class="sr-only" role="status" aria-live="polite">{{ filterResultStatus }}</p>
    <p class="sr-only" role="status" aria-live="polite">{{ sortStatus }}</p>

    <UiCard class="mt-6 overflow-hidden" :padded="false">
      <UiLoadingState v-if="loading" label="Загрузка товаров…" />
      <UiEmptyState v-else-if="!products.length" label="Товары не найдены." />
      <UiTable v-else min-width="min-w-[1180px]" label="Таблица товаров" role="region" tabindex="0">
        <caption class="sr-only">Товары каталога. Заголовки SKU, Наименование, Создан и Изменён управляют сортировкой.</caption>
        <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold tracking-wide text-gray-500">
          <tr>
            <th scope="col" :aria-sort="ariaSort('name')" class="px-4 py-3"><UiButton type="button" variant="ghost" size="sm" class="text-left" :disabled="loading" @click="changeSort('name')">Наименование<ArrowUp v-if="sort === 'name' && direction === 'asc'" :size="15" /><ArrowDown v-else-if="sort === 'name'" :size="15" /><ArrowUpDown v-else :size="15" class="text-gray-400" /></UiButton></th>
            <th scope="col" :aria-sort="ariaSort('sku')" class="px-4 py-3"><UiButton type="button" variant="ghost" size="sm" :disabled="loading" @click="changeSort('sku')">SKU<ArrowUp v-if="sort === 'sku' && direction === 'asc'" :size="15" /><ArrowDown v-else-if="sort === 'sku'" :size="15" /><ArrowUpDown v-else :size="15" class="text-gray-400" /></UiButton></th>
            <th scope="col" class="px-4 py-3">Артикул</th><th scope="col" class="px-4 py-3">Категория</th><th scope="col" class="px-4 py-3 text-right">Цена</th><th scope="col" class="px-4 py-3 text-right">Остаток</th><th scope="col" class="px-4 py-3">Статус</th>
            <th scope="col" :aria-sort="ariaSort('created_at')" class="px-4 py-3"><UiButton type="button" variant="ghost" size="sm" :disabled="loading" @click="changeSort('created_at')">Создан<ArrowUp v-if="sort === 'created_at' && direction === 'asc'" :size="15" /><ArrowDown v-else-if="sort === 'created_at'" :size="15" /><ArrowUpDown v-else :size="15" class="text-gray-400" /></UiButton></th>
            <th scope="col" :aria-sort="ariaSort('updated_at')" class="px-4 py-3"><UiButton type="button" variant="ghost" size="sm" :disabled="loading" @click="changeSort('updated_at')">Изменён<ArrowUp v-if="sort === 'updated_at' && direction === 'asc'" :size="15" /><ArrowDown v-else-if="sort === 'updated_at'" :size="15" /><ArrowUpDown v-else :size="15" class="text-gray-400" /></UiButton></th>
            <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Действия</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="product in products" :key="product.id" class="group transition hover:bg-gray-50">
            <td class="px-4 py-3"><div class="flex min-w-[240px] items-center gap-3"><img v-if="product.primary_image" :src="product.primary_image.url" :alt="product.primary_image.alt || product.name" class="h-11 w-11 shrink-0 rounded-lg object-cover"><span v-else class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><Package :size="19" aria-hidden="true" /></span><div class="min-w-0"><p class="font-semibold text-gray-800" :title="isProductNameTruncated(product.name) ? product.name : undefined"><span data-testid="product-name-preview" :aria-hidden="isProductNameTruncated(product.name) ? 'true' : undefined">{{ productNamePreview(product.name) }}</span><span v-if="isProductNameTruncated(product.name)" class="sr-only">{{ product.name }}</span></p><p class="mt-0.5 text-xs text-gray-500">{{ product.brand?.name || 'Без бренда' }}</p></div></div></td>
            <td class="whitespace-nowrap px-4 py-3 font-mono text-gray-700">{{ product.sku }}</td><td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ product.article_number || '—' }}</td><td class="max-w-48 px-4 py-3"><UiBadge tone="primary">{{ product.category.name }}</UiBadge></td><td class="whitespace-nowrap px-4 py-3 text-right font-medium text-gray-800">{{ product.price }} ₽</td><td class="whitespace-nowrap px-4 py-3 text-right text-gray-700">{{ product.stock_quantity }}</td>
            <td class="px-4 py-3"><div class="flex min-w-24 flex-col items-start gap-1"><UiBadge :tone="product.is_active ? 'success' : 'neutral'">{{ product.is_active ? 'Активен' : 'Скрыт' }}</UiBadge><UiBadge v-if="product.is_on_sale" tone="warning">Распродажа</UiBadge></div></td>
            <td class="whitespace-nowrap px-4 py-3 text-gray-600"><time :datetime="product.created_at">{{ formatDate(product.created_at) }}</time></td><td class="whitespace-nowrap px-4 py-3 text-gray-600"><time :datetime="product.updated_at">{{ formatDate(product.updated_at) }}</time></td>
            <td class="px-4 py-3"><div class="flex justify-end gap-1"><UiButton type="button" variant="ghost" size="sm" :aria-label="`Создать похожий товар ${product.name}`" @click="cloneProduct(product)"><Copy :size="17" /></UiButton><UiButton type="button" variant="ghost" size="sm" class="text-primary-600" :aria-label="`Редактировать товар ${product.name}`" @click="open(product)"><Pencil :size="17" /></UiButton><UiButton type="button" variant="danger-ghost" size="sm" :aria-label="`Удалить товар ${product.name}`" @click="confirmError = ''; deleting = product"><Trash2 :size="17" /></UiButton></div></td>
          </tr>
        </tbody>
      </UiTable>
    </UiCard>
    <UiPagination v-if="pagination" :meta="pagination" :loading="loading" :announce="false" @change="load" />

    <UiDialog :open="opened" labelledby="product-editor-title" :close-disabled="saving" :suspended="Boolean(imageDeleting || groupDeleting)" overlay-class="z-50 p-3 sm:p-6" panel-class="mx-auto flex h-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-xl" @close="close">
      <header class="flex shrink-0 justify-between border-b border-gray-200 px-5 py-4"><h2 id="product-editor-title" class="text-lg font-bold text-gray-900">{{ editing ? editing.name : 'Новый товар' }}</h2><UiButton type="button" variant="ghost" size="sm" :disabled="saving" aria-label="Закрыть карточку товара" @click="close"><X :size="20" /></UiButton></header>
      <EditorSteps :steps="steps" :active="activeStep" :enabled="enabled" @select="activeStep = $event as Step" />
      <div data-testid="product-editor-body" class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">
        <UiAlert v-if="error" class="mb-4">{{ error }}</UiAlert>
        <UiAlert v-if="success" class="mb-4" tone="success" live="polite">{{ success }}</UiAlert>
        <ProductMainSection v-if="activeStep === 'main'" />
        <ProductAttributesSection v-else-if="activeStep === 'attributes'" />
        <ProductImagesSection v-else-if="activeStep === 'images'" />
        <ProductVariantsSection v-else-if="activeStep === 'group'" />
        <div v-else id="product-review" class="space-y-5"><ProductReviewSection /><ProductRelationsSection /></div>
      </div>
      <footer data-testid="product-editor-footer" class="shrink-0 border-t border-gray-200 bg-white px-5 py-4 sm:px-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <UiButton v-if="activeStep === 'main'" type="submit" form="product-main-form" :loading="saving" :disabled="saving"><Save :size="17" />{{ saving ? 'Сохранение…' : 'Сохранить и продолжить' }}</UiButton>
        <UiButton v-else-if="activeStep === 'attributes'" type="submit" form="product-attributes-form" :loading="saving" :disabled="saving">{{ saving ? 'Сохранение…' : 'Сохранить и продолжить' }}</UiButton>
        <UiButton v-else-if="activeStep === 'images'" type="button" :disabled="saving" @click="activeStep = 'group'">Продолжить</UiButton>
        <template v-else-if="activeStep === 'group'"><UiButton v-if="selectedGroupId" type="button" variant="danger-ghost" class="sm:mr-auto" :disabled="saving" @click="confirmError = ''; groupDeleting = true">Удалить группу</UiButton><UiButton type="button" variant="ghost" :disabled="saving" @click="activeStep = 'review'">Не объединять</UiButton><UiButton type="submit" form="product-group-form" :loading="saving" :disabled="saving">{{ saving ? 'Сохранение…' : 'Сохранить группу' }}</UiButton></template>
        <template v-else><UiButton type="button" variant="ghost" :disabled="saving" @click="close">Закрыть</UiButton><UiButton v-if="!form.is_active" type="button" :loading="saving" :disabled="saving" @click="publish">{{ saving ? 'Публикация…' : 'Опубликовать товар' }}</UiButton><UiButton v-else type="button" variant="secondary" :loading="saving" :disabled="saving" @click="hideProduct"><EyeOff :size="17" />{{ saving ? 'Скрытие…' : 'Скрыть товар' }}</UiButton></template>
      </div></footer>
    </UiDialog>

    <ConfirmDialog :open="Boolean(deleting)" title="Удалить товар?" description="Будет удалена только эта продаваемая позиция." :busy="saving" :error="confirmError" @close="confirmError = ''; deleting = null" @confirm="removeProduct" />
    <ConfirmDialog :open="Boolean(imageDeleting)" title="Удалить фотографию?" description="Файл будет удалён без возможности восстановления." :busy="saving" :error="confirmError" @close="confirmError = ''; imageDeleting = null" @confirm="removeImage" />
    <ConfirmDialog :open="groupDeleting" title="Удалить группу вариантов?" description="Товары останутся самостоятельными позициями." confirm-label="Удалить группу" :busy="saving" :error="confirmError" @close="confirmError = ''; groupDeleting = false" @confirm="ungroup" />
  </section>
</template>
