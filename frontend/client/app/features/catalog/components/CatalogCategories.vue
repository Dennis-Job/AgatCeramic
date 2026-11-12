<script setup lang="ts">
import type { StandaloneCatalog, CatalogQuery } from '../types/catalog'

const props = defineProps<{
  categories: StandaloneCatalog['categories']
  query: CatalogQuery
  path: string
}>()
defineEmits<{ navigate: [] }>()
const entries = computed(() => {
  const result: {
    category: StandaloneCatalog['categories'][number]
    depth: number
  }[] = []
  const seen = new Set<number>()
  function visit(
    category: StandaloneCatalog['categories'][number],
    depth: number,
  ) {
    if (seen.has(category.id)) return
    seen.add(category.id)
    result.push({ category, depth })
    for (const child of props.categories.filter(
      (child) => child.parent_id === category.id,
    ))
      visit(child, depth + 1)
  }
  for (const category of props.categories.filter(
    (category) =>
      !category.parent_id ||
      !props.categories.some((parent) => parent.id === category.parent_id),
  ))
    visit(category, 0)
  for (const category of props.categories) visit(category, 0)
  return result
})
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
  <nav class="catalog-categories" aria-label="Категории каталога">
    <h2>Категории</h2>
    <ul>
      <li>
        <NuxtLink
          :to="link()"
          :aria-current="!query.category ? 'page' : undefined"
          @click="$emit('navigate')"
          >Все товары</NuxtLink
        >
      </li>
      <li
        v-for="{ category, depth } in entries"
        :key="category.id"
        :style="{ '--category-depth': Math.min(depth, 4) }"
      >
        <NuxtLink
          :to="link(category.slug)"
          :aria-current="query.category === category.slug ? 'page' : undefined"
          @click="$emit('navigate')"
          >{{ category.name }}</NuxtLink
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
  display: grid;
  margin: 0;
  padding: 0;
  list-style: none;
}
li {
  min-width: 0;
  padding-left: calc(var(--category-depth, 0) * 12px);
}
a {
  display: block;
  min-height: 44px;
  border-bottom: 1px solid transparent;
  padding: 12px 0;
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
</style>
