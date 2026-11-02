<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
import ProductSaleBadge from './ProductSaleBadge.vue'
import { productUnitLabel } from '~/utils/productUnits'
import { formatMoney } from '~/utils/formatMoney'
import { productPath } from '../services/products'
import type { ProductCard } from '../types/product'
const props = withDefaults(
  defineProps<{
    product: ProductCard
    row?: boolean
    action?: boolean
    busy?: boolean
  }>(),
  { row: false, action: false, busy: false },
)
defineEmits<{ add: [id: number] }>()
const failed = ref(false)
watch(
  () => props.product.image_url,
  () => {
    failed.value = false
  },
)
const canBuy = computed(
  () =>
    props.product.is_on_sale &&
    props.product.in_stock &&
    props.product.price !== null,
)
const recommendationMeta = computed(() =>
  [props.product.category.name, props.product.size?.trim()]
    .filter(Boolean)
    .join(' · '),
)
</script>
<template>
  <article class="product-card" :class="{ 'product-card--row': row }">
    <div class="card-media">
      <NuxtLink
        :to="productPath(product.slug)"
        class="card-image"
        :aria-label="'Открыть ' + product.name"
      >
        <img
          v-if="product.image_url && !failed"
          :src="product.image_url"
          :alt="product.image_alt || product.name"
          width="600"
          height="600"
          loading="lazy"
          @error="failed = true"
        /><span v-else class="image-empty">Изображение пока не добавлено</span>
      </NuxtLink>
      <ProductSaleBadge v-if="!row && product.is_on_sale" />
      <UiButton
        v-if="action && !row"
        variant="image-overlay"
        :disabled="busy || !canBuy"
        @click="$emit('add', product.id)"
        >{{ busy ? 'Добавляем…' : 'В корзину +' }}</UiButton
      >
    </div>
    <div class="card-info">
      <p v-if="!row && product.brand" class="eyebrow card-brand">
        {{ product.brand.name }}
      </p>
      <h3>
        <NuxtLink :to="productPath(product.slug)">{{ product.name }}</NuxtLink>
      </h3>
      <p v-if="row" class="availability">
        {{ product.brand?.name || product.category.name }} ·
        {{ product.in_stock ? 'В наличии' : 'Нет в наличии' }}
      </p>
      <p v-else class="card-meta">{{ recommendationMeta }}</p>
    </div>
    <div class="card-price">
      <span>{{
        product.price === null ? 'Цена по запросу' : formatMoney(product.price)
      }}</span
      ><small v-if="product.price !== null">
        / {{ productUnitLabel(product.unit) }}</small
      >
      <del
        v-if="
          product.old_price &&
          product.price &&
          Number(product.old_price) > Number(product.price)
        "
        >{{ formatMoney(product.old_price) }}</del
      >
    </div>
    <UiButton
      v-if="action && row"
      variant="outline"
      size="compact"
      :disabled="busy || !canBuy"
      @click="$emit('add', product.id)"
      >{{ busy ? 'Добавляем…' : 'Добавить' }}</UiButton
    >
  </article>
</template>
<style scoped>
.product-card {
  min-width: 0;
  overflow-wrap: anywhere;
}
.card-media {
  position: relative;
  overflow: hidden;
}
.product-card:hover,
.card-media:focus-within {
  --image-action-offset: 0%;
}
.card-image {
  display: grid;
  place-items: center;
  position: relative;
  aspect-ratio: 1 / 1.06;
  overflow: hidden;
  background: var(--color-beige);
}
.card-image img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 1s var(--ease-out);
}
.card-image:focus-visible {
  outline-offset: -4px;
}
.product-card:hover .card-image img {
  transform: scale(1.06);
}
.image-empty {
  padding: 20px;
  color: var(--color-muted);
  text-align: center;
  font-size: 12px;
}
.card-info {
  margin-top: 18px;
}
.card-brand {
  margin: 0 0 4px;
  font-size: 10px;
  letter-spacing: 0.17em;
}
h3 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 500;
  line-height: 1.4;
}
h3 a:hover {
  color: var(--color-muted);
}
.availability,
.card-meta {
  margin: 0;
  color: var(--color-muted);
  font-size: 12px;
  letter-spacing: 0.04em;
}
.card-price {
  padding-top: 8px;
  font-size: 14px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}
.card-price small {
  font-size: 12px;
  color: var(--color-muted);
}
.card-price del {
  margin-left: 10px;
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 400;
}
.product-card > :last-child:not(.card-price):not(.card-info) {
  margin-top: 17px;
  width: 100%;
}
.product-card--row {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 24px;
  padding: 24px 0;
  border-top: 1px solid var(--color-line);
}
.product-card--row:last-child {
  border-bottom: 1px solid var(--color-line);
}
.product-card--row .card-image {
  width: 64px;
  height: 64px;
  aspect-ratio: 1;
}
.product-card--row .card-image img {
  object-fit: contain;
  transition-duration: 0.7s;
}
.product-card--row:hover .card-image img {
  transform: scale(1.025);
}
.product-card--row .image-empty {
  padding: 4px;
  font-size: 9px;
  line-height: 1.3;
}
.product-card--row .card-info {
  margin: 0;
}
.product-card--row .card-price {
  padding: 0;
  text-align: right;
  font-size: 14px;
}
.product-card--row h3 {
  margin: 0 0 5px;
  font-size: 15px;
  line-height: 1.4;
}
.product-card--row .availability {
  font-size: 12px;
  letter-spacing: 0.03em;
}
.product-card--row .card-price small {
  font-size: 12px;
}
.product-card--row > :last-child:not(.card-price):not(.card-info) {
  margin: 0;
  width: auto;
}
@media (max-width: 900px) {
  .product-card--row {
    grid-template-columns: 56px minmax(0, 1fr) auto;
    gap: 16px;
  }
  .product-card--row .card-image {
    width: 56px;
    height: 56px;
  }
  .product-card--row .card-price {
    grid-column: 2;
    grid-row: 2;
    text-align: left;
  }
  .product-card--row > :last-child:not(.card-price):not(.card-info) {
    grid-column: 3;
    grid-row: 1 / 3;
  }
}
@media (max-width: 520px) {
  h3 {
    font-size: 13px;
  }
  .product-card--row {
    grid-template-columns: 56px minmax(0, 1fr);
  }
  .product-card--row .card-price {
    grid-column: 2;
  }
  .product-card--row > :last-child:not(.card-price):not(.card-info) {
    grid-column: 2;
    grid-row: 3;
    justify-self: stretch;
  }
}
</style>
