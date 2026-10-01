<script setup lang="ts">
import { ListFilter, Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import type { Category } from '../types/category.types'
defineProps<{
  categories: Category[]
  loading: boolean
  canManage: boolean
  categoryName: (id: number | null | undefined) => string | null
}>()
const emit = defineEmits<{
  details: [category: Category]
  edit: [category: Category]
  configure: [category: Category]
  remove: [category: Category]
}>()
</script>
<template>
  <UiLoadingState v-if="loading" label="Загрузка категорий…" />
  <UiTable
    v-else-if="categories.length"
    label="Список категорий"
    min-width="min-w-[960px]"
    table-class="seller-table"
    sticky-header
    :sticky-edges="canManage"
  >
    <thead>
      <tr>
        <th scope="col">Категория</th>
        <th scope="col" class="w-64">Родитель / подкатегории</th>
        <th scope="col" class="w-28 text-right">Порядок</th>
        <th scope="col" class="w-32">Статус</th>
        <th v-if="canManage" scope="col" class="w-40">
          <span class="sr-only">Действия</span>
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="category in categories" :key="category.id">
        <td>
          <UiButton
            variant="ghost"
            size="sm"
            class="text-left"
            @click="emit('details', category)"
            >{{ category.name }}</UiButton
          >
          <p class="mt-1 text-xs text-gray-500">/{{ category.slug }}</p>
        </td>
        <td>
          <p>{{ categoryName(category.parent_id) ?? '—' }}</p>
          <p v-if="category.is_parent" class="mt-1 text-xs text-primary-600">
            Родительская · Подкатегорий: {{ category.children?.length ?? 0 }}
          </p>
        </td>
        <td class="text-right">{{ category.sort_order }}</td>
        <td>
          <UiBadge :tone="category.is_active ? 'success' : 'neutral'">{{
            category.is_active ? 'Активна' : 'Скрыта'
          }}</UiBadge>
        </td>
        <td v-if="canManage">
          <div class="flex justify-end gap-1">
            <UiButton
              variant="ghost"
              size="sm"
              :aria-label="`Настроить характеристики категории ${category.name}`"
              @click="emit('configure', category)"
              ><ListFilter :size="17" /></UiButton
            ><UiButton
              variant="ghost"
              size="sm"
              :aria-label="`Редактировать категорию ${category.name}`"
              @click="emit('edit', category)"
              ><Pencil :size="17" /></UiButton
            ><UiButton
              variant="ghost"
              size="sm"
              :aria-label="`Удалить категорию ${category.name}`"
              @click="emit('remove', category)"
              ><Trash2 :size="17"
            /></UiButton>
          </div>
        </td>
      </tr></tbody
  ></UiTable>
  <UiEmptyState v-else label="Категорий пока нет." />
</template>
