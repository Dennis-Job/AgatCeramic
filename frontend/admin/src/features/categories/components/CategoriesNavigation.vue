<script setup lang="ts">
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import type { CategoryNavigationItem } from '../types/category.types'
defineProps<{
  items: CategoryNavigationItem[]
  total: number
  selectedCategoryId: string
  search: string
}>()
const emit = defineEmits<{
  'update:selectedCategoryId': [value: string]
  'update:search': [value: string]
}>()
</script>
<template>
  <h2 class="mb-4 text-xl font-bold text-gray-500">Категории</h2>
  <label class="sr-only" for="category-navigation-search"
    >Поиск категорий</label
  >
  <UiInput
    id="category-navigation-search"
    :model-value="search"
    type="search"
    placeholder="Найти категорию"
    class="mb-4 w-full"
    @update:model-value="emit('update:search', $event)"
  />
  <nav aria-label="Выбор категории">
    <UiButton
      variant="surface"
      class="category-nav-button"
      :class="{ 'category-nav-button--selected': !selectedCategoryId }"
      :aria-current="!selectedCategoryId ? 'true' : undefined"
      @click="emit('update:selectedCategoryId', '')"
      ><span>Все категории</span
      ><span class="category-nav-count">{{ total }}</span></UiButton
    >
    <ol class="category-nav-list">
      <li
        v-for="item in items"
        :key="item.category.id"
        :style="{ '--category-depth': Math.min(item.depth, 3) }"
        :class="{ 'category-nav-child': item.depth }"
      >
        <UiButton
          variant="surface"
          class="category-nav-button"
          :class="{
            'category-nav-button--selected':
              selectedCategoryId === String(item.category.id),
          }"
          :aria-current="
            selectedCategoryId === String(item.category.id) ? 'true' : undefined
          "
          :tooltip="item.path"
          @click="emit('update:selectedCategoryId', String(item.category.id))"
        >
          <span class="min-w-0"
            ><span class="block break-words [overflow-wrap:anywhere]">{{
              item.category.name
            }}</span
            ><span
              class="mt-1 block break-words text-xs font-normal text-gray-500 [overflow-wrap:anywhere]"
              >/{{ item.category.slug }}</span
            ></span
          >
          <span
            class="category-nav-count"
            :aria-label="
              item.count === null
                ? 'Количество характеристик недоступно'
                : `Характеристик: ${item.count}`
            "
            >{{ item.count ?? '—' }}</span
          >
        </UiButton>
      </li>
    </ol>
    <p
      v-if="!items.length && search.trim()"
      role="status"
      class="mt-3 text-sm text-gray-500"
    >
      Категории не найдены. Попробуйте другой запрос.
    </p>
  </nav>
  <p class="mt-4 text-xs text-gray-500">
    Число рядом с категорией — назначенные характеристики.
  </p>
</template>
<style scoped>
.category-nav-list {
  list-style: none;
}
.category-nav-child {
  padding-left: calc(var(--category-depth) * var(--admin-spacing-3));
  border-left: var(--admin-border-width) solid var(--admin-color-gray-200);
}
.category-nav-button {
  width: 100%;
  justify-content: space-between;
  text-align: left;
  font-weight: 400;
  gap: var(--admin-spacing-2);
  margin-bottom: var(--admin-spacing-1);
}
.category-nav-button--selected {
  background: var(--admin-color-primary-50);
  color: var(--admin-color-primary-500);
}
.category-nav-count {
  flex-shrink: 0;
  min-width: 28px;
  padding: 2px 6px;
  text-align: center;
  border-radius: 999px;
  background: var(--admin-color-primary-25);
}
</style>
