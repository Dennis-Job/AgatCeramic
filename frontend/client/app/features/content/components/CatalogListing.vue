<script setup lang="ts">
import { fetchCatalog } from '~/services/contentPages'
import PageState from '~/components/shared/PageState.vue'
import SectionHeading from '~/components/shared/SectionHeading.vue'
import ProductCardList from '~/features/product/components/ProductCardList.vue'

defineProps<{ title: string; description: string }>()
const route = useRoute()
const filters = computed(() => {
  const result: { category?: string; brand?: string; collection?: string } = {}
  for (const key of ['category', 'brand', 'collection'] as const) {
    const value = route.query[key]
    if (typeof value === 'string' && value.trim() && value.length <= 255)
      result[key] = value
  }
  if (!result.brand) delete result.collection
  return result
})
const contextLabel = computed(() =>
  [
    filters.value.category
      ? catalog.value?.categories.find(
          (category) => category.slug === filters.value.category,
        )?.name
      : '',
    filters.value.brand ? catalog.value?.data[0]?.brand?.name : '',
    filters.value.collection,
  ]
    .filter(Boolean)
    .join(' · '),
)
const page = computed(() => {
  const raw = route.query.page
  return typeof raw === 'string' && /^[1-9]\d{0,5}$/.test(raw) ? Number(raw) : 1
})
const { requestBase, publicBase } = usePublicApiConfig()
const {
  data: catalog,
  status,
  error,
  refresh,
} = await useAsyncData(
  () => `catalog:${page.value}:${JSON.stringify(filters.value)}`,
  () => fetchCatalog(requestBase, publicBase, page.value, filters.value),
)
function pageLink(next: number) {
  return {
    path: route.path,
    query: { ...filters.value, ...(next === 1 ? {} : { page: next }) },
    hash: '#catalog-products',
  }
}
</script>

<template>
  <section id="catalog-products" class="section catalog-section">
    <div class="container">
      <SectionHeading
        eyebrow="Коллекция материалов"
        :title="title"
        :description="description"
      />
      <p v-if="contextLabel" class="catalog-context">
        {{ contextLabel }} · <NuxtLink to="/catalog">Весь каталог</NuxtLink>
      </p>
      <PageState
        v-if="error || status === 'pending'"
        title="Товары"
        :message="
          error ? 'Не удалось загрузить каталог.' : 'Загружаем каталог…'
        "
        :error="!!error"
        :loading="status === 'pending'"
        @retry="refresh()"
      />
      <template v-else>
        <div
          v-if="catalog?.categories.length"
          class="categories"
          aria-label="Категории каталога"
        >
          <NuxtLink
            v-for="category in catalog.categories"
            :key="category.id"
            :to="{ path: '/catalog', query: { category: category.slug } }"
            >{{ category.name }}</NuxtLink
          >
        </div>
        <ProductCardList
          v-if="catalog?.data.length"
          :products="catalog.data"
          class="products"
        />
        <PageState
          v-else
          title="Товары пока не опубликованы"
          message="Каталог пополняется. Доступные материалы появятся здесь."
        />
        <nav
          v-if="catalog && catalog.meta.last_page > 1"
          class="pagination"
          aria-label="Страницы каталога"
        >
          <NuxtLink v-if="page > 1" :to="pageLink(page - 1)"
            >← Предыдущая</NuxtLink
          >
          <span aria-current="page"
            >{{ catalog.meta.current_page }} /
            {{ catalog.meta.last_page }}</span
          >
          <NuxtLink
            v-if="page < catalog.meta.last_page"
            :to="pageLink(page + 1)"
            >Следующая →</NuxtLink
          >
        </nav>
      </template>
    </div>
  </section>
</template>

<style scoped>
.catalog-section {
  scroll-margin-top: 90px;
}
.catalog-context {
  margin: -20px 0 40px;
  text-align: center;
  font-size: 13px;
  line-height: 1.7;
  overflow-wrap: anywhere;
}
.catalog-context a {
  color: var(--color-muted);
}
.categories {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 16px 32px;
  margin-bottom: 48px;
  color: var(--color-muted);
  font-size: 13px;
  overflow-wrap: anywhere;
}
.pagination {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 24px;
  margin-top: 64px;
  font-size: 13px;
}
.pagination a {
  padding-block: 14px;
  border-bottom: 1px solid var(--color-line);
}
</style>
