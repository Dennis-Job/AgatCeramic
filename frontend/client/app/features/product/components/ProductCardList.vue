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
  gap: 32px;
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
}
@media (max-width: 520px) {
  .product-card-list {
    grid-template-columns: 1fr;
  }
}
</style>
