<script setup lang="ts">
import ProductCardList from './ProductCardList.vue'
import SectionHeading from '~/components/shared/SectionHeading.vue'
import type { ProductDetail } from '../types/product'
defineProps<{ product: ProductDetail }>()
</script>
<template>
  <div>
    <section
      v-if="product.related.length"
      class="related-section"
      aria-labelledby="related-products-title"
    >
      <div class="container">
        <SectionHeading
          eyebrow="Сопутствующие товары"
          title="Что ещё понадобится"
          description="К этому товару"
          title-id="related-products-title"
        />
        <ProductCardList
          :products="product.related"
          row
          class="related-products"
        />
      </div>
    </section>
    <section
      v-if="product.recommended.length"
      class="recommended-section"
      aria-labelledby="recommended-products-title"
    >
      <div class="container">
        <SectionHeading
          eyebrow="Обратите внимание"
          title="Рекомендуем посмотреть"
          title-id="recommended-products-title"
        />
        <ProductCardList
          :products="product.recommended"
          class="recommended-grid"
        />
      </div>
    </section>
  </div>
</template>
<style scoped>
.related-section,
.recommended-section {
  padding-block: 110px;
}
.recommended-section {
  background: var(--color-bg-soft);
  border-top: 1px solid var(--color-line);
}
@media (max-width: 900px) {
  .related-section,
  .recommended-section {
    padding-block: 80px;
  }
}
</style>
