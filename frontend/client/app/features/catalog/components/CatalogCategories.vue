<script setup lang="ts">
import type { StandaloneCatalog, CatalogQuery } from '../types/catalog'
import CatalogCategoryBranch from './CatalogCategoryBranch.vue'
import { buildCategoryTree, flattenCategoryTree } from '../utils/categoryTree'

const props = defineProps<{
  categories: StandaloneCatalog['categories']
  query: CatalogQuery
  path: string
  vertical?: boolean
}>()
defineEmits<{ navigate: []; select: [category: string] }>()
const tree = computed(() => buildCategoryTree(props.categories))
const entries = computed(() => flattenCategoryTree(tree.value))
function link(category?: string) {
  const query = { ...props.query }
  delete query.page
  delete query.attributes
  if (category) query.category = category
  else delete query.category
  return { path: props.path, query, hash: '#catalog-products' }
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
    <h2 v-if="vertical">Категории</h2>
    <ul v-if="vertical" class="catalog-categories__tree">
      <li class="ui-drawer-navigation__item">
        <button
          type="button"
          class="ui-drawer-navigation__link"
          :class="{ 'ui-drawer-navigation__link--current': !query.category }"
          :aria-current="!query.category ? 'true' : undefined"
          @click="$emit('select', '')"
        >
          <span>Все товары</span>
          <span class="ui-drawer-navigation__number" aria-hidden="true"
            >01</span
          >
        </button>
      </li>
      <CatalogCategoryBranch
        v-for="(node, index) in tree"
        :key="node.category.id"
        :node="node"
        :number="index + 2"
        :selected="query.category"
        @select="$emit('select', $event)"
      />
    </ul>
    <ul v-else>
      <li>
        <NuxtLink
          :to="link()"
          :aria-current="!query.category ? 'page' : undefined"
          @click="$emit('navigate')"
          ><span>Все товары</span></NuxtLink
        >
      </li>
      <li v-for="category in entries" :key="category.id">
        <NuxtLink
          :to="link(category.slug)"
          :aria-current="query.category === category.slug ? 'page' : undefined"
          @click="$emit('navigate')"
          ><span>{{ category.name }}</span></NuxtLink
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
