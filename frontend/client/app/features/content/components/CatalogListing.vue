<script setup lang="ts">
import { fetchCatalog } from '~/services/contentPages'
import PageState from '~/components/shared/PageState.vue'
import SectionHeading from '~/components/shared/SectionHeading.vue'
import type { CatalogProduct } from '~/types/contentPage'

defineProps<{ title: string; description: string }>()
const route = useRoute()
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
  () => `catalog:${page.value}`,
  () => fetchCatalog(requestBase, publicBase, page.value),
)
const units: Record<CatalogProduct['unit'], string> = {
  piece: 'шт.',
  square_meter: 'м²',
  linear_meter: 'пог. м',
  package: 'уп.',
  kilogram: 'кг',
  liter: 'л',
  set: 'компл.',
}
const rubles = new Intl.NumberFormat('ru-RU', {
  style: 'currency',
  currency: 'RUB',
  maximumFractionDigits: 2,
})
function priceLabel(product: CatalogProduct) {
  return product.price === null
    ? 'Цена по запросу'
    : `${rubles.format(Number(product.price))} / ${units[product.unit]}`
}
function pageLink(next: number) {
  return {
    path: route.path,
    query: next === 1 ? {} : { page: next },
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
          <span v-for="category in catalog.categories" :key="category.id">{{
            category.name
          }}</span>
        </div>
        <div v-if="catalog?.data.length" class="products">
          <article
            v-for="product in catalog.data"
            :key="product.id"
            class="product"
          >
            <div class="product__image">
              <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.image_alt || product.name"
                width="600"
                height="600"
                loading="lazy"
                @error="product.image_url = null"
              />
              <span v-else>Изображение пока не добавлено</span>
            </div>
            <p class="product__category">
              {{ product.category.name
              }}<template v-if="product.brand">
                · {{ product.brand.name }}</template
              >
            </p>
            <h3>{{ product.name }}</h3>
            <p v-if="product.description" class="product__description">
              {{ product.description }}
            </p>
            <p class="product__price">{{ priceLabel(product) }}</p>
          </article>
        </div>
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
.products {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 48px 28px;
}
.product {
  min-width: 0;
  overflow-wrap: anywhere;
}
.product__image {
  display: grid;
  aspect-ratio: 1;
  place-items: center;
  background: var(--color-beige);
  overflow: hidden;
}
.product__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.product__image span {
  padding: 24px;
  color: var(--color-muted);
  font-size: 13px;
  text-align: center;
}
.product__category {
  margin: 22px 0 10px;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.6;
}
h3 {
  margin: 0 0 14px;
  font-size: 19px;
  font-weight: 500;
  line-height: 1.4;
}
.product__description {
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.7;
  white-space: pre-line;
}
.product__price {
  margin: 20px 0 0;
  font-size: 15px;
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
@media (max-width: 1024px) {
  .products {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 640px) {
  .products {
    grid-template-columns: 1fr;
  }
}
</style>
