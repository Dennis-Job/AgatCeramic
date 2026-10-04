<script setup lang="ts">
import { computed } from 'vue'
import { formatMoney } from '../../../utils/formatMoney'
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  Copy,
  Download,
  EyeOff,
  Pencil,
  Plus,
  Save,
  Trash2,
  X,
} from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiSegmentedControl from '../../../components/ui/UiSegmentedControl.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import EditorSteps from './ProductEditorSteps.vue'
import ProductMainSection from './ProductMainSection.vue'
import ProductAttributesSection from './ProductAttributesSection.vue'
import ProductImagesSection from './ProductImagesSection.vue'
import ProductPhotoAction from './ProductPhotoAction.vue'
import ProductVariantsSection from './ProductVariantsSection.vue'
import ProductRelationsSection from './ProductRelationsSection.vue'
import ProductReviewSection from './ProductReviewSection.vue'
import { provideProductEditorContext } from '../composables/useProductEditorContext'
import { useProductEditor } from '../composables/useProductEditor'
import { productUnitLabel } from '../productPresentation'

const editor = useProductEditor()
provideProductEditorContext(editor)
const {
  products,
  pagination,
  error,
  loading,
  opened,
  photosOnly,
  photosLoading,
  photosLoadFailed,
  saving,
  editing,
  deleting,
  activeStep,
  steps,
  success,
  confirmError,
  filterResultStatus,
  filterCountsLoading,
  filterCountsError,
  exportStatus,
  exporting,
  productCountUnavailable,
  filters,
  sort,
  direction,
  form,
  selectedGroupId,
  groupDeleting,
  imageDeleting,
  canManage,
  canManageImports,
  sortStatus,
  activityOptions,
  saleOptions,
  filterCategoryOptions,
  filterBrandOptions,
  hasActiveFilters,
  productCountLabel,
  ariaSort,
  formatDate,
  load,
  resetFilters,
  changeSort,
  exportFilteredProducts,
  enabled,
  open,
  openPhotos,
  loadPhotos,
  cloneProduct,
  close,
  removeProduct,
  removeImage,
  ungroup,
  publish,
  hideProduct,
} = editor
type Step = typeof activeStep.value
const footerNotes = computed(() => {
  if (photosOnly.value)
    return ['Загрузка, удаление и порядок фото сохраняются сразу.']
  if (activeStep.value === 'main')
    return editing.value
      ? []
      : [
          'Новый товар сохранится как черновик. Публикация доступна на шаге «Проверка».',
        ]
  if (activeStep.value === 'attributes')
    return [
      selectedGroupId.value
        ? 'Различающиеся характеристики изменяются только у этой позиции. Общие характеристики автоматически применяются ко всем товарам группы.'
        : 'Характеристики принадлежат только этой позиции. Черновик можно сохранить незаполненным.',
    ]
  if (activeStep.value === 'images')
    return ['Загрузка, удаление и порядок фото сохраняются сразу.']
  if (activeStep.value === 'group')
    return [
      'Объедините самостоятельные товары и выберите различающиеся характеристики.',
      'Первые 25 совпадений той же категории и бренда. Уточните поиск, если товара нет.',
    ]
  if (activeStep.value === 'review')
    return [
      'Проверьте основные данные перед завершением.',
      'Добавьте товары, которые стоит предложить покупателю вместе с этой позицией.',
    ]
  return []
})

function requestProductDeletion(
  product: (typeof products.value)[number],
): void {
  confirmError.value = ''
  deleting.value = product
}

function requestGroupDeletion(): void {
  confirmError.value = ''
  groupDeleting.value = true
}

function closeProductDeletion(): void {
  confirmError.value = ''
  deleting.value = null
}

function closeImageDeletion(): void {
  confirmError.value = ''
  imageDeleting.value = null
}

function closeGroupDeletion(): void {
  confirmError.value = ''
  groupDeleting.value = false
}
</script>

<template>
  <AdminWorkspace mode="list" :aria-busy="loading">
    <template #intro>
      <PageHeader class="mb-6" eyebrow="Каталог" title="Товары">
        <template #actions>
          <UiButton
            v-if="canManageImports"
            type="button"
            variant="secondary"
            :loading="exporting"
            :disabled="exporting"
            :aria-busy="exporting"
            @click="exportFilteredProducts"
            ><Download :size="18" aria-hidden="true" />{{
              exporting ? 'Экспорт…' : 'Скачать Excel'
            }}</UiButton
          >
          <UiButton v-if="canManage" type="button" @click="open()"
            ><Plus :size="18" aria-hidden="true" />Добавить товар</UiButton
          >
        </template>
      </PageHeader>
    </template>

    <UiNotification v-if="error && !opened">{{ error }}</UiNotification>
    <UiNotification v-if="exportStatus" tone="success" live="polite">{{
      exportStatus
    }}</UiNotification>

    <form
      class="admin-container rounded-xl bg-gray-50 p-4 sm:p-5"
      role="search"
      @submit.prevent
    >
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <UiInput
          v-model="filters.search"
          class="xl:col-span-2"
          aria-label="Поиск"
          searchable
          placeholder="Название, SKU, артикул"
        />
        <UiSelect
          v-model="filters.category_id"
          :options="filterCategoryOptions"
          accessible-name="Категория"
          search-placeholder="Начните вводить название категории"
          searchable
        />
        <UiSelect
          v-model="filters.brand_id"
          :options="filterBrandOptions"
          accessible-name="Бренд"
          search-placeholder="Начните вводить название бренда"
          searchable
        />
      </div>
      <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <UiSegmentedControl
          v-model="filters.is_active"
          label="Активность"
          hide-label
          :counts-loading="filterCountsLoading"
          name="product-activity-filter"
          :options="activityOptions"
        />
        <UiSegmentedControl
          v-model="filters.is_on_sale"
          label="Распродажа"
          hide-label
          :counts-loading="filterCountsLoading"
          name="product-sale-filter"
          :options="saleOptions"
        />
      </div>
      <p
        v-if="filterCountsError"
        class="mt-2 text-xs text-gray-500"
        role="alert"
      >
        {{ filterCountsError }}
      </p>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
        <UiBadge
          data-testid="product-count"
          :tone="
            productCountUnavailable ? 'danger' : loading ? 'neutral' : 'primary'
          "
          >{{ productCountLabel }}</UiBadge
        ><UiButton
          type="button"
          variant="surface"
          :disabled="!hasActiveFilters"
          @click="resetFilters"
          >Сбросить</UiButton
        >
      </div>
    </form>
    <p class="sr-only" role="status" aria-live="polite">
      {{ filterResultStatus }}
    </p>
    <p class="sr-only" role="status" aria-live="polite">{{ sortStatus }}</p>

    <div class="mt-6 min-w-0">
      <UiLoadingState
        v-if="loading"
        class="admin-container"
        label="Загрузка товаров…"
      />
      <UiEmptyState
        v-else-if="!products.length"
        class="admin-container"
        label="Товары не найдены."
      />
      <UiTable
        v-else
        full-bleed
        min-width="min-w-[1560px]"
        table-class="product-table"
        sticky-header="page"
        sticky-edges
        label="Таблица товаров"
        role="region"
        tabindex="0"
      >
        <caption class="sr-only">
          Товары каталога. Заголовки SKU, Наименование, Создан и Изменён
          управляют сортировкой.
        </caption>
        <colgroup>
          <col class="product-table-name-column" />
          <col style="width: 130px" />
          <col style="width: 130px" />
          <col style="width: 160px" />
          <col style="width: 130px" />
          <col style="width: 100px" />
          <col style="width: 130px" />
          <col style="width: 130px" />
          <col style="width: 130px" />
          <col style="width: 160px" />
        </colgroup>
        <thead
          class="border-b border-gray-200 bg-gray-50 text-xs font-semibold tracking-wide text-gray-500"
        >
          <tr>
            <th scope="col" :aria-sort="ariaSort('name')" class="px-4 py-3">
              <UiButton
                type="button"
                variant="surface"
                size="sm"
                class="text-left"
                :disabled="loading"
                @click="changeSort('name')"
                >Наименование<ArrowUp
                  v-if="sort === 'name' && direction === 'asc'"
                  :size="15" /><ArrowDown
                  v-else-if="sort === 'name'"
                  :size="15" /><ArrowUpDown
                  v-else
                  :size="15"
                  class="text-gray-500"
              /></UiButton>
            </th>
            <th scope="col" :aria-sort="ariaSort('sku')" class="px-4 py-3">
              <UiButton
                type="button"
                variant="surface"
                size="sm"
                :disabled="loading"
                @click="changeSort('sku')"
                >SKU<ArrowUp
                  v-if="sort === 'sku' && direction === 'asc'"
                  :size="15" /><ArrowDown
                  v-else-if="sort === 'sku'"
                  :size="15" /><ArrowUpDown
                  v-else
                  :size="15"
                  class="text-gray-500"
              /></UiButton>
            </th>
            <th scope="col" class="px-4 py-3">Артикул</th>
            <th scope="col" class="px-4 py-3">Категория</th>
            <th scope="col" class="px-4 py-3 text-right">Цена</th>
            <th scope="col" class="px-4 py-3 text-right">Остаток</th>
            <th scope="col" class="px-4 py-3">Статус</th>
            <th
              scope="col"
              :aria-sort="ariaSort('created_at')"
              class="px-4 py-3"
            >
              <UiButton
                type="button"
                variant="surface"
                size="sm"
                :disabled="loading"
                @click="changeSort('created_at')"
                >Создан<ArrowUp
                  v-if="sort === 'created_at' && direction === 'asc'"
                  :size="15" /><ArrowDown
                  v-else-if="sort === 'created_at'"
                  :size="15" /><ArrowUpDown
                  v-else
                  :size="15"
                  class="text-gray-500"
              /></UiButton>
            </th>
            <th
              scope="col"
              :aria-sort="ariaSort('updated_at')"
              class="px-4 py-3"
            >
              <UiButton
                type="button"
                variant="surface"
                size="sm"
                :disabled="loading"
                @click="changeSort('updated_at')"
                >Изменён<ArrowUp
                  v-if="sort === 'updated_at' && direction === 'asc'"
                  :size="15" /><ArrowDown
                  v-else-if="sort === 'updated_at'"
                  :size="15" /><ArrowUpDown
                  v-else
                  :size="15"
                  class="text-gray-500"
              /></UiButton>
            </th>
            <th scope="col" class="px-4 py-3 text-right">
              <span class="sr-only">Действия</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr
            v-for="product in products"
            :key="product.id"
            class="group transition hover:bg-gray-50"
          >
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <ProductPhotoAction
                  :url="product.primary_image?.url || null"
                  :alt="product.primary_image?.alt || product.name"
                  :name="product.name"
                  :editable="canManage"
                  @edit="openPhotos(product)"
                />
                <div class="min-w-0">
                  <p
                    class="font-semibold text-gray-500"
                    data-testid="product-name-preview"
                  >
                    {{ product.name }}
                  </p>
                  <p class="mt-0.5 text-xs text-gray-500">
                    {{ product.brand?.name || 'Без бренда' }}
                  </p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 font-mono text-gray-500">
              {{ product.sku }}
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ product.article_number || '—' }}
            </td>
            <td class="max-w-48 px-4 py-3">
              <UiBadge tone="additional">{{ product.category.name }}</UiBadge>
            </td>
            <td class="px-4 py-3 text-right font-medium text-gray-500">
              {{ formatMoney(product.price) }}
              <p class="mt-0.5 text-xs font-normal text-gray-500">
                за {{ productUnitLabel(product.unit) }}
              </p>
            </td>
            <td class="px-4 py-3 text-right text-gray-500">
              {{ product.stock_quantity }}
              <p class="mt-0.5 text-xs text-gray-500">
                {{ productUnitLabel(product.unit) }}
              </p>
            </td>
            <td class="px-4 py-3">
              <div class="flex min-w-24 flex-col items-start gap-1">
                <UiBadge :tone="product.is_active ? 'success' : 'neutral'">{{
                  product.is_active ? 'Активен' : 'Скрыт'
                }}</UiBadge
                ><UiBadge v-if="product.is_on_sale" tone="warning"
                  >Распродажа</UiBadge
                >
              </div>
            </td>
            <td class="px-4 py-3 text-gray-500">
              <time :datetime="product.created_at">{{
                formatDate(product.created_at)
              }}</time>
            </td>
            <td class="px-4 py-3 text-gray-500">
              <time :datetime="product.updated_at">{{
                formatDate(product.updated_at)
              }}</time>
            </td>
            <td class="px-4 py-3">
              <div class="flex">
                <UiButton
                  type="button"
                  variant="neutral-ghost"
                  size="sm"
                  tooltip="Копировать товар — создать похожий"
                  :aria-label="`Создать похожий товар ${product.name}`"
                  @click="cloneProduct(product)"
                  ><Copy :size="17" /></UiButton
                ><UiButton
                  type="button"
                  variant="primary-ghost"
                  size="sm"
                  tooltip="Редактировать товар"
                  :aria-label="`Редактировать товар ${product.name}`"
                  @click="open(product)"
                  ><Pencil :size="17" /></UiButton
                ><UiButton
                  type="button"
                  variant="danger-ghost"
                  size="sm"
                  tooltip="Удалить товар"
                  :aria-label="`Удалить товар ${product.name}`"
                  @click="requestProductDeletion(product)"
                  ><Trash2 :size="17"
                /></UiButton>
              </div>
            </td>
          </tr>
        </tbody>
      </UiTable>
    </div>
    <UiPagination
      v-if="pagination"
      class="admin-container"
      :meta="pagination"
      :loading="loading"
      :announce="false"
      @change="load"
    />

    <UiDialog
      :open="opened"
      labelledby="product-editor-title"
      :close-disabled="saving"
      :suspended="Boolean(imageDeleting || groupDeleting)"
      overlay-class="z-50 p-3 sm:p-6"
      panel-class="mx-auto flex h-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-xl"
      @close="close"
    >
      <header
        class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-5 py-4"
      >
        <div class="min-w-0 [overflow-wrap:anywhere]">
          <h2
            id="product-editor-title"
            class="admin-focus text-lg font-bold text-gray-500 focus:outline-none"
            :data-autofocus="photosOnly ? '' : undefined"
            :tabindex="photosOnly ? -1 : undefined"
          >
            {{
              photosOnly
                ? editing?.primary_image
                  ? 'Редактирование фото'
                  : 'Добавление фото'
                : editing
                  ? editing.name
                  : 'Новый товар'
            }}
          </h2>
          <p v-if="photosOnly" class="mt-1 text-sm text-gray-500">
            {{ editing?.name }}
          </p>
        </div>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          class="shrink-0"
          :disabled="saving"
          :aria-label="
            photosOnly ? 'Закрыть фотографии товара' : 'Закрыть карточку товара'
          "
          @click="close"
          ><X :size="20"
        /></UiButton>
      </header>
      <EditorSteps
        v-if="!photosOnly"
        :steps="steps"
        :active="activeStep"
        :enabled="enabled"
        @select="activeStep = $event as Step"
      />
      <div
        data-testid="product-editor-body"
        class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6"
      >
        <UiNotification v-if="error">{{ error }}</UiNotification>
        <UiNotification v-if="success" tone="success" live="polite">{{
          success
        }}</UiNotification>
        <template v-if="photosOnly">
          <UiLoadingState v-if="photosLoading" label="Загрузка фотографий…" />
          <div v-else-if="photosLoadFailed" class="space-y-4">
            <p class="text-sm text-gray-500" role="status">
              Не удалось загрузить фотографии товара.
            </p>
            <UiButton type="button" variant="secondary" @click="loadPhotos"
              >Повторить загрузку</UiButton
            >
          </div>
          <ProductImagesSection v-else />
        </template>
        <ProductMainSection v-else-if="activeStep === 'main'" />
        <ProductAttributesSection v-else-if="activeStep === 'attributes'" />
        <ProductImagesSection v-else-if="activeStep === 'images'" />
        <ProductVariantsSection v-else-if="activeStep === 'group'" />
        <div v-else id="product-review" class="space-y-5">
          <ProductReviewSection /><ProductRelationsSection />
        </div>
      </div>
      <UiDialogFooter flush data-testid="product-editor-footer">
        <template v-if="footerNotes.length" #note>
          <span v-for="note in footerNotes" :key="note" class="block">{{
            note
          }}</span>
        </template>
        <template v-if="photosOnly">
          <UiButton type="button" :disabled="saving" @click="close"
            >Готово</UiButton
          >
        </template>
        <UiButton
          v-else-if="activeStep === 'main'"
          type="submit"
          form="product-main-form"
          :loading="saving"
          :disabled="saving"
          ><Save :size="17" />{{
            saving ? 'Сохранение…' : 'Сохранить и продолжить'
          }}</UiButton
        >
        <UiButton
          v-else-if="activeStep === 'attributes'"
          type="submit"
          form="product-attributes-form"
          :loading="saving"
          :disabled="saving"
          >{{ saving ? 'Сохранение…' : 'Сохранить и продолжить' }}</UiButton
        >
        <UiButton
          v-else-if="activeStep === 'images'"
          type="button"
          :disabled="saving"
          @click="activeStep = 'group'"
          >Продолжить</UiButton
        >
        <template v-else-if="activeStep === 'group'"
          ><UiButton
            v-if="selectedGroupId"
            type="button"
            variant="danger-ghost"
            class="sm:mr-auto"
            :disabled="saving"
            @click="requestGroupDeletion"
            >Удалить группу</UiButton
          ><UiButton
            type="button"
            variant="surface"
            :disabled="saving"
            @click="activeStep = 'review'"
            >Не объединять</UiButton
          ><UiButton
            type="submit"
            form="product-group-form"
            :loading="saving"
            :disabled="saving"
            >{{ saving ? 'Сохранение…' : 'Сохранить группу' }}</UiButton
          ></template
        >
        <template v-else
          ><UiButton
            type="button"
            variant="surface"
            :disabled="saving"
            @click="close"
            >Закрыть</UiButton
          ><UiButton
            v-if="!form.is_active"
            type="button"
            :loading="saving"
            :disabled="saving"
            @click="publish"
            >{{ saving ? 'Публикация…' : 'Опубликовать товар' }}</UiButton
          ><UiButton
            v-else
            type="button"
            variant="secondary"
            :loading="saving"
            :disabled="saving"
            @click="hideProduct"
            ><EyeOff :size="17" />{{
              saving ? 'Скрытие…' : 'Скрыть товар'
            }}</UiButton
          ></template
        >
      </UiDialogFooter>
    </UiDialog>

    <ConfirmDialog
      :open="Boolean(deleting)"
      title="Удалить товар?"
      description="Будет удалена только эта продаваемая позиция."
      :busy="saving"
      :error="confirmError"
      @close="closeProductDeletion"
      @confirm="removeProduct"
    />
    <ConfirmDialog
      :open="Boolean(imageDeleting)"
      title="Удалить фотографию?"
      description="Файл будет удалён без возможности восстановления."
      :busy="saving"
      :error="confirmError"
      @close="closeImageDeletion"
      @confirm="removeImage"
    />
    <ConfirmDialog
      :open="groupDeleting"
      title="Удалить группу вариантов?"
      description="Товары останутся самостоятельными позициями."
      confirm-label="Удалить группу"
      :busy="saving"
      :error="confirmError"
      @close="closeGroupDeletion"
      @confirm="ungroup"
    />
  </AdminWorkspace>
</template>

<style scoped>
:deep(.product-table) {
  table-layout: fixed;
}
:deep(.product-table td) {
  overflow-wrap: anywhere;
}
.product-table-name-column {
  /* The name receives all remaining width beyond the fixed detail columns. */
  width: auto;
}
</style>
