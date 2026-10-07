<script setup lang="ts">
import { computed, nextTick } from 'vue'
import { Folder, ListFilter, Pencil, Trash2 } from '@lucide/vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import CategoryAttributeOverview from './CategoryAttributeOverview.vue'
import type { Category, CategoryOverview } from '../types/category.types'

const props = defineProps<{
  categories: Category[]
  loading: boolean
  canManage: boolean
  selectedCategoryId: string
  overview: Record<number, CategoryOverview>
  categoryName: (id: number | null | undefined) => string | null
}>()
const emit = defineEmits<{
  details: [category: Category]
  edit: [category: Category]
  configure: [category: Category]
  remove: [category: Category]
  retry: [id: number]
  'update:selectedCategoryId': [value: string]
}>()
const rows = computed(() => {
  const byId = new Map(
    props.categories.map((category) => [category.id, category]),
  )
  const selectedId = props.selectedCategoryId
  const matched = new Set(selectedId ? [Number(selectedId)] : [])
  const allRows = props.categories.map((category) => {
    const ancestors: number[] = []
    let parent = category.parent_id
    const seen = new Set([category.id])
    while (parent != null && byId.has(parent) && !seen.has(parent)) {
      seen.add(parent)
      ancestors.unshift(parent)
      parent = byId.get(parent)?.parent_id
    }
    return { category, ancestors }
  })
  const context = new Set(
    allRows
      .filter((row) => matched.has(row.category.id))
      .flatMap((row) => row.ancestors),
  )
  return allRows.filter(
    (row) =>
      !selectedId ||
      matched.has(row.category.id) ||
      context.has(row.category.id) ||
      row.ancestors.some((id) => matched.has(id)),
  )
})

async function navigateTo(id: number): Promise<void> {
  emit('update:selectedCategoryId', '')
  await nextTick()
  const category = document.getElementById(`category-${id}`)
  category?.focus({ preventScroll: true })
  category?.scrollIntoView({ block: 'start', behavior: 'instant' })
}
</script>
<template>
  <UiLoadingState
    v-if="loading"
    class="admin-container"
    label="Загрузка категорий…"
  />
  <div v-else-if="categories.length" class="admin-container">
    <ol
      v-if="rows.length"
      aria-label="Категории и их характеристики"
      class="category-tree"
    >
      <li
        v-for="{ category, ancestors } in rows"
        :key="category.id"
        class="category-tree-item"
        :class="{ 'category-tree-item--child': ancestors.length }"
        :style="{ '--category-depth': Math.min(ancestors.length, 3) }"
      >
        <article
          :id="`category-${category.id}`"
          tabindex="-1"
          :aria-labelledby="`category-title-${category.id}`"
          class="category-overview admin-focus"
        >
          <header class="category-overview-header">
            <Folder
              class="category-folder shrink-0 text-gray-500"
              :size="26"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <h2 :id="`category-title-${category.id}`">
                <UiButton
                  variant="surface"
                  class="category-name"
                  @click="emit('details', category)"
                  >{{ category.name }}</UiButton
                >
              </h2>
              <p class="mt-1 break-words text-sm text-gray-500">
                /{{ category.slug }}
              </p>
              <p class="mt-2 text-xs text-gray-500">
                Порядок: {{ category.sort_order
                }}<template v-if="category.sku_prefix">
                  · Префикс SKU: {{ category.sku_prefix }}</template
                >
              </p>
            </div>
            <div class="category-relations min-w-0 text-sm text-gray-500">
              <div class="mb-2 flex flex-wrap gap-2">
                <UiBadge :tone="category.is_parent ? 'primary' : 'neutral'">{{
                  category.is_parent ? 'Родительская' : 'Обычная'
                }}</UiBadge>
                <UiBadge :tone="category.is_active ? 'success' : 'neutral'">{{
                  category.is_active ? 'Активна' : 'Скрыта'
                }}</UiBadge>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-1">
                <span>Родитель:</span>
                <UiButton
                  v-if="
                    category.parent_id != null &&
                    categoryName(category.parent_id)
                  "
                  variant="primary-ghost"
                  size="sm"
                  class="category-relation-link"
                  @click="navigateTo(category.parent_id)"
                  >{{ categoryName(category.parent_id) }}</UiButton
                >
                <span v-else>Без родителя</span>
              </div>
              <div class="mt-1 flex flex-wrap items-baseline gap-x-1 gap-y-1">
                <span
                  >Подкатегории<template v-if="category.children?.length">
                    ({{ category.children.length }})</template
                  >:</span
                >
                <template v-if="category.children?.length">
                  <UiButton
                    v-for="child in category.children"
                    :key="child.id"
                    variant="primary-ghost"
                    size="sm"
                    class="category-relation-link"
                    @click="navigateTo(child.id)"
                    >{{ child.name }}</UiButton
                  >
                </template>
                <span v-else>Нет подкатегорий</span>
              </div>
            </div>
            <div v-if="canManage" class="category-actions flex gap-1">
              <UiButton
                variant="surface"
                size="sm"
                tooltip="Настроить характеристики"
                :aria-label="`Настроить характеристики категории ${category.name}`"
                @click="emit('configure', category)"
                ><ListFilter :size="17"
              /></UiButton>
              <UiButton
                variant="primary-ghost"
                size="sm"
                tooltip="Редактировать категорию"
                :aria-label="`Редактировать категорию ${category.name}`"
                @click="emit('edit', category)"
                ><Pencil :size="17"
              /></UiButton>
              <UiButton
                variant="danger-ghost"
                size="sm"
                tooltip="Удалить категорию"
                :aria-label="`Удалить категорию ${category.name}`"
                @click="emit('remove', category)"
                ><Trash2 :size="17"
              /></UiButton>
            </div>
          </header>
          <p
            v-if="category.description"
            class="category-description whitespace-pre-wrap break-words text-sm text-gray-500"
          >
            {{ category.description }}
          </p>
          <CategoryAttributeOverview
            class="category-attributes"
            :overview="overview[category.id]"
            :category-name="category.name"
            @retry="emit('retry', category.id)"
          />
        </article>
      </li>
    </ol>
    <UiEmptyState
      v-else
      label="Категории не найдены. Попробуйте другой запрос."
    />
  </div>
  <UiEmptyState v-else class="admin-container" label="Категорий пока нет." />
</template>
<style scoped>
.category-tree {
  list-style: none;
}
.category-tree-item {
  padding-block: var(--admin-spacing-6);
  border-top: var(--admin-border-width) solid var(--admin-color-gray-100);
}
.category-overview {
  min-width: 0;
  scroll-margin-top: calc(var(--admin-shell-height) + var(--admin-spacing-4));
}
.category-overview-header {
  display: grid;
  grid-template-columns: 26px minmax(0, 1fr);
  align-items: start;
  column-gap: var(--admin-spacing-4);
  row-gap: var(--admin-spacing-3);
}
.category-name {
  min-height: 0;
  padding: 0;
  justify-content: start;
  text-align: left;
  font-size: 1.25rem;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.category-relations,
.category-actions {
  grid-column: 2;
}
.category-relation-link {
  min-height: 0;
  padding: 0;
  font-size: inherit;
  font-weight: inherit;
  text-align: left;
  overflow-wrap: anywhere;
}
.category-description,
.category-attributes {
  margin-top: var(--admin-spacing-4);
}
.category-folder {
  margin-top: var(--admin-spacing-1);
}
@media (min-width: 640px) {
  .category-tree-item--child {
    padding-left: calc(var(--category-depth) * 36px);
    border-left: var(--admin-border-width) solid var(--admin-color-gray-200);
  }
  .category-overview-header {
    grid-template-columns: 26px minmax(0, 1fr) auto;
  }
  .category-actions {
    grid-column: 3;
    grid-row: 1;
  }
  .category-description,
  .category-attributes {
    margin-left: 42px;
  }
}
@media (min-width: 1024px) {
  .category-overview-header {
    grid-template-columns: 26px minmax(180px, 0.65fr) minmax(0, 1fr) auto;
  }
  .category-relations {
    grid-column: 3;
  }
  .category-actions {
    grid-column: 4;
  }
}
</style>
