<script setup lang="ts">
import ProductCard from './ProductCard.vue'
import type { ProductCard as ProductCardData } from '../types/product'
import { useCart } from '~/features/cart/composables/useCart'

const props = withDefaults(
  defineProps<{ products: ProductCardData[]; row?: boolean }>(),
  { row: false },
)
const { addItem, busy, error } = useCart()
const { showSuccess } = useSuccessNotification()
const localError = ref('')
watch(
  () => props.products,
  () => {
    localError.value = ''
  },
)
async function add(id: number) {
  localError.value = ''
  if (await addItem(id, 1)) showSuccess('Товар добавлен в корзину.')
  else localError.value = error.value || 'Не удалось добавить товар.'
}
</script>

<template>
  <div class="product-card-list" :class="{ 'product-card-list--rows': row }">
    <p v-if="localError" class="product-card-list__error" role="alert">
      {{ localError }}
    </p>
    <ProductCard
      v-for="product in products"
      :key="product.id"
      :product="product"
      :row="row"
      action
      :busy="busy"
      @add="add"
    />
  </div>
</template>

<style scoped>
.product-card-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  column-gap: 32px;
  row-gap: 0;
}
/* Internal subgrid tracks have no gutters; only complete card rows are spaced. */
.product-card-list :deep(.product-card:not(.product-card--row)) {
  margin-bottom: 32px;
}
.product-card-list :deep(.product-card:nth-last-child(-n + 3)) {
  margin-bottom: 0;
}
.product-card-list--rows {
  display: block;
  max-width: 900px;
  margin-inline: auto;
}
.product-card-list__error {
  grid-column: 1 / -1;
  margin: 0;
  padding-block: 20px;
  font-size: 13px;
  overflow-wrap: anywhere;
}
@media (max-width: 900px) {
  .product-card-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .product-card-list :deep(.product-card:not(.product-card--row)) {
    margin-bottom: 32px;
  }
  .product-card-list :deep(.product-card:nth-last-child(-n + 2)) {
    margin-bottom: 0;
  }
}
@media (max-width: 520px) {
  .product-card-list {
    grid-template-columns: 1fr;
  }
  .product-card-list :deep(.product-card:not(.product-card--row)) {
    margin-bottom: 32px;
  }
  .product-card-list :deep(.product-card:last-child) {
    margin-bottom: 0;
  }
}
</style>
