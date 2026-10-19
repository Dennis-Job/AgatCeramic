<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import {
  ArrowDownWideNarrow,
  ChevronDown,
  Circle,
  CircleDot,
} from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiPopover from '../../../components/ui/UiPopover.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
import type { ProductSort } from '../types/product.types'

const { sort, direction, loading, setSort } = useProductEditorContext()
const open = ref(false)
const id = useId()
const fields: Array<{ value: ProductSort; label: string; short: string }> = [
  { value: 'sku', label: 'SKU', short: 'SKU' },
  { value: 'name', label: 'Наименованию', short: 'Название' },
  { value: 'created_at', label: 'Дате создания', short: 'Создан' },
  { value: 'updated_at', label: 'Дате изменения', short: 'Изменён' },
]
const orders = computed(() => {
  if (sort.value === 'created_at' || sort.value === 'updated_at')
    return { asc: 'Сначала старые', desc: 'Сначала новые' }
  if (sort.value === 'name') return { asc: 'А–Я', desc: 'Я–А' }
  return { asc: 'По возрастанию', desc: 'По убыванию' }
})
const label = computed(
  () =>
    `${fields.find((field) => field.value === sort.value)!.short}: ${orders.value[direction.value].toLocaleLowerCase('ru')}`,
)
const displayLabel = computed(() =>
  sort.value === 'name'
    ? `Название: ${orders.value[direction.value]}`
    : label.value,
)
function chooseField(field: ProductSort) {
  const order =
    field === sort.value
      ? direction.value
      : field === 'created_at' || field === 'updated_at'
        ? 'desc'
        : 'asc'
  void setSort(field, order)
}
</script>

<template>
  <UiPopover
    v-model:open="open"
    align="end"
    label="Сортировка товаров"
    panel-class="product-sort-menu"
  >
    <template #trigger="{ trigger }">
      <UiButton
        v-bind="trigger"
        type="button"
        variant="secondary"
        class="max-w-full text-left"
        :aria-busy="loading"
        :aria-label="`Сортировка: ${displayLabel}`"
        aria-haspopup="menu"
      >
        <ArrowDownWideNarrow :size="18" class="shrink-0" aria-hidden="true" />
        <span class="min-w-0 [overflow-wrap:anywhere]">{{ displayLabel }}</span>
        <ChevronDown :size="16" class="shrink-0" aria-hidden="true" />
      </UiButton>
    </template>
    <div role="menu" aria-label="Параметры сортировки">
      <div role="group" :aria-labelledby="`${id}-field`">
        <p
          :id="`${id}-field`"
          class="px-3 pb-2 text-xs font-semibold text-gray-500"
        >
          Сортировать по
        </p>
        <UiButton
          v-for="field in fields"
          :key="field.value"
          type="button"
          variant="surface"
          class="w-full justify-start text-left"
          role="menuitemradio"
          :aria-checked="sort === field.value"
          :aria-disabled="loading"
          @click="chooseField(field.value)"
        >
          <component
            :is="sort === field.value ? CircleDot : Circle"
            :size="18"
            class="shrink-0"
            :class="sort === field.value ? 'text-primary-500' : 'text-gray-400'"
            aria-hidden="true"
          />{{ field.label }}
        </UiButton>
      </div>
      <div
        role="group"
        :aria-labelledby="`${id}-order`"
        class="mt-2 border-t border-gray-100 pt-3"
      >
        <p
          :id="`${id}-order`"
          class="px-3 pb-2 text-xs font-semibold text-gray-500"
        >
          Порядок
        </p>
        <UiButton
          v-for="order in ['desc', 'asc'] as const"
          :key="order"
          type="button"
          variant="surface"
          class="w-full justify-start text-left"
          role="menuitemradio"
          :aria-checked="direction === order"
          :aria-disabled="loading"
          @click="setSort(sort, order)"
        >
          <component
            :is="direction === order ? CircleDot : Circle"
            :size="18"
            class="shrink-0"
            :class="direction === order ? 'text-primary-500' : 'text-gray-400'"
            aria-hidden="true"
          />{{ orders[order] }}
        </UiButton>
      </div>
    </div>
  </UiPopover>
</template>

<style scoped>
:deep(.product-sort-menu) {
  padding: var(--admin-spacing-3);
}
</style>
