<script setup lang="ts">
import type { CatalogCategoryNode } from '../utils/categoryTree'
import type { CatalogQuery } from '../types/catalog'
import { catalogCategoryLocation } from '../utils/catalogLocation'

defineProps<{
  nodes: CatalogCategoryNode[]
  query: CatalogQuery
  path: string
  showCounts?: boolean
}>()
defineEmits<{ navigate: [] }>()
</script>

<template>
  <ul class="catalog-category-links">
    <li v-for="node in nodes" :key="node.category.id">
      <NuxtLink
        :to="catalogCategoryLocation(path, query, node.category.slug)"
        :aria-current="
          query.category === node.category.slug ? 'page' : undefined
        "
        :aria-label="node.category.name"
        :aria-description="
          showCounts
            ? `Товаров в категории: ${node.category.product_count}`
            : undefined
        "
        @click="$emit('navigate')"
      >
        {{ node.category.name }}
        <span
          v-if="showCounts"
          class="catalog-category-links__count"
          aria-hidden="true"
          >{{ node.category.product_count }}</span
        >
      </NuxtLink>
      <CatalogCategoryLinks
        v-if="node.children.length"
        :nodes="node.children"
        :query="query"
        :path="path"
        :show-counts="showCounts"
        @navigate="$emit('navigate')"
      />
    </li>
  </ul>
</template>

<style scoped>
.catalog-category-links {
  margin: 0;
  padding: 0;
  list-style: none;
}
.catalog-category-links .catalog-category-links {
  margin-left: 12px;
}
.catalog-category-links__count {
  margin-left: 8px;
  color: var(--color-muted);
}
</style>
