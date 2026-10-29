<script setup lang="ts">
import { ShoppingBag } from '@lucide/vue'
import UiButton from '~/components/ui/UiButton.vue'
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
    current?: boolean
  }>(),
  { row: false, action: false, busy: false, current: false },
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
</script>
<template>
  <article class="product-card" :class="{ 'product-card--row': row }">
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
      <span v-if="current" class="current-label">Вы смотрите</span>
    </NuxtLink>
    <div class="card-info">
      <p class="eyebrow">{{ product.brand?.name || product.category.name }}</p>
      <h3>
        <NuxtLink :to="productPath(product.slug)">{{ product.name }}</NuxtLink>
      </h3>
      <p class="availability">
        {{ product.in_stock ? 'В наличии' : 'Нет в наличии' }}
      </p>
    </div>
    <div class="card-price">
      <del
        v-if="
          product.old_price &&
          product.price &&
          Number(product.old_price) > Number(product.price)
        "
        >{{ formatMoney(product.old_price) }}</del
      ><span>{{
        product.price === null ? 'Цена по запросу' : formatMoney(product.price)
      }}</span
      ><small v-if="product.price !== null">
        / {{ productUnitLabel(product.unit) }}</small
      >
    </div>
    <UiButton
      v-if="action"
      :disabled="busy || !canBuy"
      @click="$emit('add', product.id)"
      >{{ busy ? 'Добавляем…' : 'Добавить'
      }}<ShoppingBag :size="16" :stroke-width="1.6" aria-hidden="true"
    /></UiButton>
  </article>
</template>
<style scoped>
.product-card {
  min-width: 0;
  overflow-wrap: anywhere;
}
.card-image {
  display: grid;
  place-items: center;
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--color-beige);
}
.card-image img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: transform 0.7s var(--ease-out);
}
.card-image:hover img {
  transform: scale(1.025);
}
.image-empty {
  padding: 20px;
  color: var(--color-muted);
  text-align: center;
  font-size: 12px;
}
.current-label {
  position: absolute;
  left: 14px;
  bottom: 14px;
  padding: 7px 10px;
  background: var(--color-card-label-surface);
  font-size: 9px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.card-info {
  margin-top: 16px;
}
.eyebrow {
  font-size: 10px;
  letter-spacing: 0.17em;
}
h3 {
  margin: 4px 0 8px;
  font-size: 15px;
  font-weight: 500;
  line-height: 1.6;
}
h3 a:hover {
  color: var(--color-muted);
}
.availability {
  margin: 0;
  color: var(--color-muted);
  font-size: 11px;
}
.card-price {
  padding-top: 12px;
  font-size: 15px;
  font-variant-numeric: tabular-nums;
}
.card-price small {
  font-size: 11px;
  color: var(--color-muted);
}
.card-price del {
  display: block;
  margin-bottom: 3px;
  color: var(--color-muted);
  font-size: 12px;
}
.product-card > :last-child:not(.card-price):not(.card-info) {
  margin-top: 17px;
  width: 100%;
}
.product-card--row {
  display: grid;
  grid-template-columns: 106px minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 26px;
  padding: 24px 0;
  border-block: 1px solid var(--color-line);
}
.product-card--row .card-image {
  width: 106px;
  height: 106px;
  padding: 8px;
  background: var(--color-white);
}
.product-card--row .card-info {
  margin: 0;
}
.product-card--row .card-price {
  padding: 0;
  white-space: nowrap;
  font-size: 17px;
}
.product-card--row > :last-child:not(.card-price):not(.card-info) {
  margin: 0;
  width: auto;
}
@media (max-width: 1024px) {
  .product-card--row {
    grid-template-columns: 90px minmax(0, 1fr) auto;
    gap: 20px;
  }
  .product-card--row .card-image {
    width: 90px;
    height: 90px;
  }
  .product-card--row > :last-child:not(.card-price):not(.card-info) {
    grid-column: 2/-1;
    justify-self: start;
  }
}
@media (max-width: 520px) {
  h3 {
    font-size: 13px;
  }
  .card-info .eyebrow {
    font-size: 9px;
  }
  .product-card--row {
    grid-template-columns: 76px minmax(0, 1fr);
    gap: 15px;
  }
  .product-card--row .card-image {
    width: 76px;
    height: 76px;
  }
  .product-card--row .card-price {
    grid-column: 2;
    font-size: 15px;
  }
  .product-card--row > :last-child:not(.card-price):not(.card-info) {
    grid-column: 2;
    justify-self: stretch;
  }
}
</style>
