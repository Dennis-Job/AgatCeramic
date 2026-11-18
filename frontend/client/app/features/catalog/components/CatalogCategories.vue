<script setup lang="ts">
import type { StandaloneCatalog, CatalogQuery } from '../types/catalog'
import CatalogCategoryBranch from './CatalogCategoryBranch.vue'
import CatalogCategoryLinks from './CatalogCategoryLinks.vue'
import UiNavigationDropdown from '~/components/ui/UiNavigationDropdown.vue'
import {
  buildCategoryTree,
  categoryBranchContains,
} from '../utils/categoryTree'
import { catalogCategoryLocation } from '../utils/catalogLocation'

const props = defineProps<{
  categories: StandaloneCatalog['categories']
  query: CatalogQuery
  path: string
  vertical?: boolean
  brand?: string
  total?: number
}>()
const emit = defineEmits<{ navigate: []; select: [category: string] }>()
const tree = computed(() => buildCategoryTree(props.categories))
function link(category?: string) {
  return catalogCategoryLocation(props.path, props.query, category)
}
function navigate(close: () => void) {
  close()
  emit('navigate')
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
    <ul v-else class="catalog-categories__roots">
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
      <li v-for="node in tree" :key="node.category.id">
        <UiNavigationDropdown
          v-if="node.children.length"
          :label="node.category.name"
          :current="categoryBranchContains(node, query.category)"
        >
          <template v-if="brand" #suffix>
            <span class="catalog-categories__count" aria-hidden="true">{{
              node.category.product_count
            }}</span>
          </template>
          <template #default="{ close }">
            <NuxtLink
              :to="link(node.category.slug)"
              :aria-current="
                query.category === node.category.slug ? 'page' : undefined
              "
              @click="navigate(close)"
              >Все товары категории</NuxtLink
            >
            <CatalogCategoryLinks
              :nodes="node.children"
              :query="query"
              :path="path"
              :show-counts="!!brand"
              @navigate="navigate(close)"
            />
          </template>
        </UiNavigationDropdown>
        <NuxtLink
          v-else
          :to="link(node.category.slug)"
          :aria-current="
            query.category === node.category.slug ? 'page' : undefined
          "
          :aria-label="node.category.name"
          :aria-description="
            brand && node.category.product_count !== undefined
              ? `Товаров в категории: ${node.category.product_count}`
              : undefined
          "
          @click="$emit('navigate')"
          ><span>{{ node.category.name }}</span
          ><span
            v-if="brand && node.category.product_count !== undefined"
            class="catalog-categories__count"
            aria-hidden="true"
            >{{ node.category.product_count }}</span
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
.catalog-categories__roots {
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
.catalog-categories__roots > li > a {
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
.catalog-categories__roots > li > a[aria-current],
.catalog-categories__roots > li > a:hover {
  color: var(--color-ink);
}
.catalog-categories__roots > li > a[aria-current] {
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
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
