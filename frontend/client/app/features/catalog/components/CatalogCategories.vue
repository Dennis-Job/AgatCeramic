<script setup lang="ts">
import type { StandaloneCatalog, CatalogQuery } from '../types/catalog'
import CatalogCategoryBranch from './CatalogCategoryBranch.vue'
import { buildCategoryTree, flattenCategoryTree } from '../utils/categoryTree'
import { catalogLocation } from '../utils/catalogLocation'

const props = defineProps<{
  categories: StandaloneCatalog['categories']
  query: CatalogQuery
  path: string
  vertical?: boolean
  brand?: string
  total?: number
}>()
defineEmits<{ navigate: []; select: [category: string] }>()
const tree = computed(() => buildCategoryTree(props.categories))
const entries = computed(() => flattenCategoryTree(tree.value))
function link(category?: string) {
  const query = { ...props.query }
  delete query.page
  delete query.attributes
  delete query.collection
  if (category) query.category = category
  else delete query.category
  return catalogLocation(props.path, query)
}
</script>

<template>
  <nav
    class="catalog-categories"
    :class="{
      'catalog-categories--vertical': vertical,
      'ui-drawer-navigation': vertical,
    }"
    aria-label="Категории каталога"
  >
    <h2 v-if="vertical">{{ brand ? 'Категории бренда' : 'Категории' }}</h2>
    <ul v-if="vertical" class="catalog-categories__tree">
      <li class="ui-drawer-navigation__item">
        <button
          type="button"
          class="ui-drawer-navigation__link"
          :class="{ 'ui-drawer-navigation__link--current': !query.category }"
          :aria-current="!query.category ? 'true' : undefined"
          :aria-label="brand ? 'Все товары бренда' : 'Все товары'"
          @click="$emit('select', '')"
        >
          <span>{{ brand ? 'Все товары бренда' : 'Все товары' }}</span>
          <span class="ui-drawer-navigation__number" aria-hidden="true">{{
            brand ? total : '01'
          }}</span>
        </button>
      </li>
      <CatalogCategoryBranch
        v-for="(node, index) in tree"
        :key="node.category.id"
        :node="node"
        :number="index + 2"
        :selected="query.category"
        :show-counts="!!brand"
        @select="$emit('select', $event)"
      />
    </ul>
    <ul v-else>
      <li>
        <NuxtLink
          :to="link()"
          :aria-current="!query.category ? 'page' : undefined"
          :aria-label="brand ? 'Все товары бренда' : 'Все товары'"
          @click="$emit('navigate')"
          ><span>{{ brand ? 'Все товары бренда' : 'Все товары' }}</span
          ><span
            v-if="brand && total !== undefined"
            class="catalog-categories__count"
            aria-hidden="true"
            >{{ total }}</span
          ></NuxtLink
        >
      </li>
      <li v-for="category in entries" :key="category.id">
        <NuxtLink
          :to="link(category.slug)"
          :aria-current="query.category === category.slug ? 'page' : undefined"
          :aria-label="category.name"
          :aria-description="
            brand && category.product_count !== undefined
              ? `Товаров в категории: ${category.product_count}`
              : undefined
          "
          @click="$emit('navigate')"
          ><span>{{ category.name }}</span
          ><span
            v-if="brand && category.product_count !== undefined"
            class="catalog-categories__count"
            aria-hidden="true"
            >{{ category.product_count }}</span
          ></NuxtLink
        >
      </li>
    </ul>
  </nav>
</template>

<style scoped>
h2 {
  margin: 0 0 16px;
  font-size: 14px;
  font-weight: 500;
}
ul {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0 32px;
  margin: 0;
  padding: 0;
  list-style: none;
}
li {
  min-width: 0;
}
a {
  display: block;
  min-height: 44px;
  border-bottom: 1px solid transparent;
  padding: 12px 0;
  text-align: center;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.catalog-categories__count {
  margin-left: 8px;
  color: var(--color-muted);
}
a[aria-current],
a:hover {
  color: var(--color-ink);
}
a[aria-current] {
  border-bottom-color: var(--color-ink);
}
.catalog-categories--vertical {
  --drawer-navigation-font-size: 13px;
}
.catalog-categories__tree {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  justify-content: stretch;
  gap: 0;
}
</style>
