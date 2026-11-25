<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue'
import { formatMoney } from '../../../utils/formatMoney'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiPagination from '../../../components/ui/UiPagination.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import type { useProductList } from '../composables/useProductList'
import { productUnitLabel } from '../productPresentation'
import type { Product } from '../types/product.types'
import ProductListControls from './ProductListControls.vue'
import ProductPhotoAction from './ProductPhotoAction.vue'
import ProductRowActions from './ProductRowActions.vue'

const { state } = defineProps<{
  state: ReturnType<typeof useProductList>
  canManage: boolean
}>()
const emit = defineEmits<{
  edit: [product: Product]
  photos: [product: Product]
  clone: [product: Product]
}>()

const {
  products,
  pagination,
  loading,
  filterResultStatus,
  sortStatus,
  ariaSort,
  sort,
  direction,
  changeSort,
  load,
  deleting,
  deletingProduct,
  confirmError,
  requestProductDeletion,
  closeProductDeletion,
  removeProduct,
} = state
</script>

<template>
  <ProductListControls :state="state" />
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
      min-width="min-w-[1040px]"
      table-class="product-table"
      sticky-header="page"
      sticky-edges
      label="Таблица товаров"
      role="region"
      tabindex="0"
    >
      <caption class="sr-only">
        Товары каталога. Заголовки SKU и Наименование управляют сортировкой.
        Сортировка по датам доступна в меню над таблицей.
      </caption>
      <colgroup>
        <col />
        <col style="width: 13%" />
        <col style="width: 12%" />
        <col style="width: 12%" />
        <col style="width: 9%" />
        <col style="width: 12%" />
        <col style="width: 1px" />
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
              >SKU / артикул<ArrowUp
                v-if="sort === 'sku' && direction === 'asc'"
                :size="15" /><ArrowDown
                v-else-if="sort === 'sku'"
                :size="15" /><ArrowUpDown
                v-else
                :size="15"
                class="text-gray-500"
            /></UiButton>
          </th>
          <th scope="col" class="px-4 py-3 text-center">Категория</th>
          <th scope="col" class="px-4 py-3 text-right">Цена</th>
          <th scope="col" class="px-4 py-3 text-right">Остаток</th>
          <th scope="col" class="px-4 py-3 text-center">Статус</th>
          <th scope="col" class="px-2 py-3 text-right">
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
                @edit="emit('photos', product)"
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
          <td class="px-4 py-3 text-gray-500">
            <p class="font-mono">{{ product.sku }}</p>
            <p class="mt-0.5 text-xs">{{ product.article_number || '—' }}</p>
          </td>
          <td class="px-4 py-3 text-center">
            <UiBadge
              tone="additional"
              class="max-w-full [overflow-wrap:normal]"
              >{{ product.category.name }}</UiBadge
            >
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
          <td class="px-4 py-3 text-center">
            <div class="flex min-w-0 flex-col items-center gap-1">
              <UiBadge :tone="product.is_active ? 'success' : 'neutral'">{{
                product.is_active ? 'Активен' : 'Скрыт'
              }}</UiBadge
              ><UiBadge v-if="product.is_on_sale" tone="warning"
                >Распродажа</UiBadge
              >
            </div>
          </td>
          <td class="px-2 py-3">
            <ProductRowActions
              class="w-max"
              :name="product.name"
              @edit="emit('edit', product)"
              @clone="emit('clone', product)"
              @delete="requestProductDeletion(product)"
            />
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
  <ConfirmDialog
    :open="Boolean(deleting)"
    title="Удалить товар?"
    description="Будет удалена только эта продаваемая позиция."
    :busy="deletingProduct"
    :error="confirmError"
    @close="closeProductDeletion"
    @confirm="removeProduct"
  />
</template>

<style scoped>
:deep(.product-table) {
  table-layout: auto;
}
:deep(.product-table td) {
  overflow-wrap: anywhere;
}
:deep(.product-table th button) {
  max-width: 100%;
  padding-inline: 0;
  background: transparent;
  text-align: left;
  white-space: normal;
}
</style>
