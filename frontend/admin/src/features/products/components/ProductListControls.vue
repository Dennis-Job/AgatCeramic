<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue'
import { ChevronDown, SlidersHorizontal, X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiSegmentedControl from '../../../components/ui/UiSegmentedControl.vue'
import ProductSortMenu from './ProductSortMenu.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
import type { ProductListFilters } from '../types/product.types'

const {
  filters,
  filterCategoryOptions,
  filterBrandOptions,
  activityOptions,
  saleOptions,
  filterCountsLoading,
  filterCountsError,
  productCountUnavailable,
  productCountLabel,
  hasActiveFilters,
  resetFilters,
} = useProductEditorContext()
const expanded = ref(false)
const id = useId()
const root = ref<HTMLFormElement | null>(null)
const chips = computed(() => {
  const values: Array<{ key: keyof ProductListFilters; label: string }> = []
  if (filters.value.search.trim())
    values.push({
      key: 'search',
      label: `Поиск: ${filters.value.search.trim()}`,
    })
  for (const [key, options] of [
    ['category_id', filterCategoryOptions.value],
    ['brand_id', filterBrandOptions.value],
    ['is_active', activityOptions.value],
    ['is_on_sale', saleOptions.value],
  ] as const) {
    const value = filters.value[key]
    if (value !== '')
      values.push({
        key,
        label: options.find((option) => option.value === value)?.label ?? value,
      })
  }
  return values
})
async function clearFilter(key: keyof ProductListFilters) {
  const buttons = Array.from(
    root.value?.querySelectorAll<HTMLButtonElement>('[data-filter-chip]') ?? [],
  )
  const focused = buttons.indexOf(document.activeElement as HTMLButtonElement)
  filters.value[key] = ''
  await nextTick()
  if (focused >= 0) {
    const remaining =
      root.value?.querySelectorAll<HTMLButtonElement>('[data-filter-chip]')
    ;(
      remaining?.item(Math.min(focused, remaining.length - 1)) ??
      root.value?.querySelector<HTMLInputElement>('input')
    )?.focus()
  }
}
async function clearAll() {
  resetFilters()
  await nextTick()
  root.value?.querySelector<HTMLInputElement>('input')?.focus()
}
</script>

<template>
  <form ref="root" class="admin-container" role="search" @submit.prevent>
    <div class="flex items-start gap-2 sm:gap-3">
      <UiInput
        v-model="filters.search"
        class="min-w-0 flex-1"
        aria-label="Поиск"
        searchable
        placeholder="Название, SKU, артикул"
      />
      <UiButton
        type="button"
        :variant="expanded || chips.length ? 'secondary' : 'surface'"
        class="shrink-0"
        :class="{
          'border border-primary-200 text-primary-500':
            expanded || chips.length,
        }"
        :aria-expanded="expanded"
        :aria-label="chips.length ? `Фильтры: ${chips.length}` : 'Фильтры'"
        :aria-controls="`${id}-panel`"
        @click="expanded = !expanded"
      >
        <SlidersHorizontal :size="18" aria-hidden="true" /><span
          >Фильтры<span v-if="chips.length" class="ml-1.5 tabular-nums">{{
            chips.length
          }}</span></span
        >
        <ChevronDown
          :size="16"
          class="hidden transition-transform sm:block"
          :class="{ 'rotate-180': expanded }"
          aria-hidden="true"
        />
      </UiButton>
    </div>
    <div
      v-show="expanded"
      :id="`${id}-panel`"
      class="mt-4 rounded-xl bg-gray-50 p-4 sm:p-5"
    >
      <div
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(8rem,1fr)_minmax(8rem,1fr)_minmax(0,max-content)_minmax(0,max-content)] lg:items-center"
      >
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
        <UiSegmentedControl
          v-model="filters.is_on_sale"
          class="sm:col-span-2 lg:col-span-1"
          label="Распродажа"
          hide-label
          :counts-loading="filterCountsLoading"
          name="product-sale-filter"
          :options="saleOptions"
          fit-content
        />
        <UiSegmentedControl
          v-model="filters.is_active"
          class="sm:col-span-2 lg:col-span-1"
          label="Активность"
          hide-label
          :counts-loading="filterCountsLoading"
          name="product-activity-filter"
          :options="activityOptions"
          fit-content
        />
      </div>
    </div>
    <div v-if="hasActiveFilters" class="mt-3 flex flex-wrap items-center gap-2">
      <UiButton
        v-for="chip in chips"
        :key="chip.key"
        type="button"
        variant="secondary"
        size="sm"
        class="max-w-full text-left"
        data-filter-chip
        :aria-label="`Убрать фильтр: ${chip.label}`"
        @click="clearFilter(chip.key)"
      >
        <span class="min-w-0 [overflow-wrap:anywhere]">{{ chip.label }}</span
        ><X :size="14" class="shrink-0" aria-hidden="true" />
      </UiButton>
      <UiButton
        type="button"
        variant="primary-ghost"
        size="sm"
        @click="clearAll"
        >Сбросить фильтры</UiButton
      >
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
      <p
        data-testid="product-count"
        class="text-sm text-gray-500"
        :class="{ 'text-error-500': productCountUnavailable }"
      >
        {{ productCountLabel }}
      </p>
      <ProductSortMenu />
    </div>
    <p v-if="filterCountsError" class="mt-2 text-xs text-gray-500" role="alert">
      {{ filterCountsError }}
    </p>
  </form>
</template>
