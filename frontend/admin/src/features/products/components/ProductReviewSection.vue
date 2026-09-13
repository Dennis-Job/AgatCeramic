<script setup lang="ts">
import { Package } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import { useProductEditorContext } from '../composables/useProductEditorContext'
const { form, editing, images, currentGroup, hasValue, attributeValues, requiredIds, attributes } = useProductEditorContext()
</script>

<template>
  <section id="product-review-summary" class="rounded-xl border border-gray-300 p-5" aria-labelledby="product-review-title">
    <header class="flex flex-wrap items-start justify-between gap-3"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><Package :size="20" /></span><div><h3 id="product-review-title" class="font-bold text-gray-900">Информация о товаре</h3><p class="text-sm text-gray-500">Проверьте основные данные перед завершением.</p></div></div><div class="flex flex-wrap justify-end gap-2"><UiBadge :tone="form.is_active ? 'success' : 'neutral'">{{ form.is_active ? 'Опубликован' : 'Скрыт' }}</UiBadge><UiBadge :tone="form.is_on_sale ? 'warning' : 'neutral'">{{ form.is_on_sale ? 'Распродажа' : 'Не на распродаже' }}</UiBadge></div></header>
    <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div class="rounded-lg bg-gray-25 px-4 py-3"><dt class="text-xs font-medium text-gray-500">SKU</dt><dd class="mt-1 break-words text-sm font-semibold text-gray-800 [overflow-wrap:anywhere]">{{editing?.sku}}</dd></div><div class="rounded-lg bg-gray-25 px-4 py-3"><dt class="text-xs font-medium text-gray-500">Цена и остаток</dt><dd class="mt-1 text-sm font-semibold text-gray-800">{{form.price}} ₽ · {{form.stock_quantity}} шт.</dd></div><div class="rounded-lg bg-gray-25 px-4 py-3"><dt class="text-xs font-medium text-gray-500">Фотографии</dt><dd class="mt-1 text-sm font-semibold text-gray-800">{{images.length}}</dd></div><div class="rounded-lg bg-gray-25 px-4 py-3"><dt class="text-xs font-medium text-gray-500">Группа вариантов</dt><dd class="mt-1 break-words text-sm font-semibold text-gray-800 [overflow-wrap:anywhere]">{{currentGroup?.name??'Не назначена'}}</dd></div></dl>
    <UiAlert v-if="requiredIds.some(id => !hasValue(attributeValues[id]))" class="mt-4" tone="warning" live="polite">До публикации заполните обязательные характеристики: {{ attributes.filter(a => requiredIds.includes(a.id) && !hasValue(attributeValues[a.id])).map(a => a.name).join(', ') }}.</UiAlert>
  </section>
</template>
