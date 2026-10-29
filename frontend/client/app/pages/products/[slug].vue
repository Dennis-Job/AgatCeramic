<script setup lang="ts">
import { setResponseStatus } from 'h3'
import ProductDetailView from '~/features/product/components/ProductDetail.vue'
import PageState from '~/components/shared/PageState.vue'
import { fetchProduct, productPath } from '~/features/product/services/products'
import { siteOrigin } from '#shared/utils/pageUrls'
const route = useRoute()
const slug = computed(() => String(route.params.slug || ''))
const { requestBase, publicBase } = usePublicApiConfig()
const {
  data: product,
  status,
  error,
  refresh,
} = await useAsyncData(
  () => 'product:' + slug.value,
  () => fetchProduct(slug.value, requestBase, publicBase),
)
const missing = computed(() => error.value?.statusCode === 404)
const event = useRequestEvent()
if (event && error.value) setResponseStatus(event, missing.value ? 404 : 503)
const origin = siteOrigin(
  useRuntimeConfig().public.siteUrl || useRequestURL().origin,
)
const canonical = computed(() =>
  new URL(productPath(product.value?.slug || slug.value), origin).toString(),
)
const description = computed(
  () =>
    product.value?.description?.slice(0, 300) ||
    (product.value
      ? product.value.name +
        (product.value.brand ? '. ' + product.value.brand.name : '') +
        '. Фото и характеристики товара.'
      : ''),
)
useSeoMeta({
  title: () =>
    product.value
      ? product.value.name + ' — AgatCeramic'
      : 'Товар — AgatCeramic',
  description,
  ogTitle: () => product.value?.name || 'Товар — AgatCeramic',
  ogDescription: description,
  ogType: 'website',
  ogUrl: canonical,
  ogImage: () =>
    product.value?.image_url
      ? new URL(product.value.image_url, origin).toString()
      : undefined,
  robots: () =>
    error.value || !product.value ? 'noindex,follow' : 'index,follow',
})
useHead(() => ({
  link: [{ rel: 'canonical', href: canonical.value }],
  script: product.value
    ? [
        {
          type: 'application/ld+json',
          innerHTML: JSON.stringify([
            {
              '@context': 'https://schema.org',
              '@type': 'Product',
              name: product.value.name,
              sku: product.value.sku,
              description: description.value,
              url: canonical.value,
              image: product.value.images.map((image) =>
                new URL(image.url, origin).toString(),
              ),
              ...(product.value.brand
                ? {
                    brand: { '@type': 'Brand', name: product.value.brand.name },
                  }
                : {}),
              ...(product.value.price !== null
                ? {
                    offers: {
                      '@type': 'Offer',
                      priceCurrency: 'RUB',
                      price: product.value.price,
                      url: canonical.value,
                      availability:
                        'https://schema.org/' +
                        (product.value.in_stock && product.value.is_on_sale
                          ? 'InStock'
                          : 'OutOfStock'),
                    },
                  }
                : {}),
            },
            {
              '@context': 'https://schema.org',
              '@type': 'BreadcrumbList',
              itemListElement: [
                {
                  '@type': 'ListItem',
                  position: 1,
                  name: 'Главная',
                  item: origin + '/',
                },
                {
                  '@type': 'ListItem',
                  position: 2,
                  name: product.value.category.name,
                  item: new URL(
                    '/catalog?category=' +
                      encodeURIComponent(product.value.category.slug),
                    origin,
                  ).toString(),
                },
                {
                  '@type': 'ListItem',
                  position: 3,
                  name: product.value.name,
                  item: canonical.value,
                },
              ],
            },
          ]).replace(/</g, '\\u003c'),
        },
      ]
    : [],
}))
</script>
<template>
  <ProductDetailView v-if="product" :product="product" />
  <div v-else class="container product-page-state">
    <h1>{{ missing ? 'Товар не найден' : 'Карточка товара' }}</h1>
    <PageState
      :title="
        missing
          ? 'Товар недоступен'
          : error
            ? 'Не удалось загрузить товар'
            : 'Загружаем товар…'
      "
      :message="
        missing
          ? 'Товар не опубликован или больше недоступен. Посмотрите другие материалы в каталоге.'
          : 'Попробуйте загрузить карточку ещё раз.'
      "
      :loading="status === 'pending'"
      :error="!!error && !missing"
      @retry="refresh()"
    /><NuxtLink to="/catalog">Перейти в каталог</NuxtLink>
  </div>
</template>
<style scoped>
.product-page-state {
  padding-block: 70px 100px;
}
.product-page-state h1 {
  margin: 0 0 30px;
  font-size: clamp(32px, 4vw, 48px);
  font-weight: 500;
  line-height: 1.2;
}
.product-page-state > a {
  display: inline-block;
  margin-top: 24px;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--color-line);
  font-size: 13px;
}
</style>
