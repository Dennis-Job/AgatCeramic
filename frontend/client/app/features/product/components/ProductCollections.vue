<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'
import ProductCard from './ProductCard.vue'
import type { ProductDetail } from '../types/product'
import { useCart } from '~/features/cart/composables/useCart'
const props = defineProps<{ product: ProductDetail }>()
const { addItem, busy, error } = useCart()
const { showSuccess } = useSuccessNotification()
const localError = ref('')
watch(
  () => props.product.id,
  () => {
    localError.value = ''
  },
)
async function add(id: number) {
  localError.value = ''
  if (await addItem(id, 1)) showSuccess('Товар добавлен в корзину.')
  else localError.value = error.value || 'Не удалось добавить товар.'
}
const collectionLink = computed(() =>
  props.product.collection
    ? {
        path: '/catalog',
        query: {
          brand: props.product.collection.brand_slug,
          collection: props.product.collection.name,
        },
      }
    : '/catalog',
)
</script>
<template>
  <div>
    <p v-if="localError" class="container collection-error" role="alert">
      {{ localError }}
    </p>
    <section v-if="product.related.length" class="related-section">
      <div class="container">
        <p class="eyebrow">К этому товару</p>
        <h2>Сопутствующие товары</h2>
        <ProductCard
          v-for="item in product.related"
          :key="item.id"
          :product="item"
          row
          action
          :busy="busy"
          @add="add"
        />
      </div>
    </section>
    <section
      v-if="product.group?.products.length"
      class="container collection-section"
    >
      <div class="section-heading">
        <div>
          <p v-if="product.brand" class="eyebrow">{{ product.brand.name }}</p>
          <h2>Варианты товара</h2>
        </div>
        <NuxtLink
          v-if="product.collection"
          :to="collectionLink"
          class="collection-link"
          >Товары коллекции <ArrowRight :size="15" aria-hidden="true"
        /></NuxtLink>
      </div>
      <div v-if="product.group?.products.length" class="collection-grid">
        <ProductCard
          v-for="item in product.group.products"
          :key="item.id"
          :product="item"
          :current="item.id === product.id"
        />
      </div>
    </section>
    <section
      v-if="product.recommended.length"
      class="container recommended-section"
    >
      <p class="eyebrow">Обратите внимание</p>
      <h2>Рекомендуем посмотреть</h2>
      <div class="recommended-grid">
        <ProductCard
          v-for="item in product.recommended"
          :key="item.id"
          :product="item"
          action
          :busy="busy"
          @add="add"
        />
      </div>
    </section>
  </div>
</template>
<style scoped>
.related-section {
  padding-block: 60px;
  border-block: 1px solid var(--color-line);
  background: var(--color-bg-soft);
}
h2 {
  margin: 8px 0 24px;
  font-size: 30px;
  font-weight: 500;
  line-height: 1.3;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}
.collection-section {
  padding-block: 76px 82px;
}
.section-heading {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 30px;
  margin-bottom: 32px;
}
.section-heading h2 {
  margin-bottom: 0;
}
.collection-link {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  max-width: 100%;
  padding-bottom: 3px;
  border-bottom: 1px solid currentColor;
  font-size: 12px;
  line-height: 1.5;
}
.collection-grid,
.recommended-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 32px;
}
.recommended-section {
  padding-block: 0 70px;
}
.collection-error {
  padding-block: 20px;
  font-size: 13px;
}
@media (max-width: 760px) {
  .related-section {
    padding-block: 42px;
  }
  .collection-section {
    padding-block: 48px;
  }
  .section-heading {
    flex-direction: column;
    align-items: start;
    gap: 20px;
  }
  h2 {
    font-size: 28px;
  }
  .collection-grid,
  .recommended-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
  }
  .recommended-section {
    padding-bottom: 48px;
  }
}
@media (max-width: 520px) {
  h2 {
    font-size: 25px;
  }
  .recommended-grid {
    grid-template-columns: 1fr;
  }
  .collection-grid {
    gap: 18px;
  }
}
</style>
