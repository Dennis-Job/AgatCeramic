<script setup lang="ts">
import ProductGallery from './ProductGallery.vue'
import ProductPurchase from './ProductPurchase.vue'
import ProductInfoTabs from './ProductInfoTabs.vue'
import ProductCollections from './ProductCollections.vue'
import type { ProductDetail } from '../types/product'
import type { SellerContacts } from '~/types/contentPage'
import { formatPhone, normalizePhoneInput } from '~/utils/phone'
defineProps<{ product: ProductDetail }>()
const tab = ref('specs')
const { data: contacts } = useNuxtData<SellerContacts>('seller-contacts')
const phone = computed(() => formatPhone(contacts.value?.phones[0]))
const phoneHref = computed(() => {
  const raw = normalizePhoneInput(contacts.value?.phones[0] || '')
  return raw && /^\+7\d{10}$/.test(raw) ? 'tel:' + raw : undefined
})
</script>
<template>
  <div>
    <div class="container">
      <nav class="product-crumbs" aria-label="Хлебные крошки">
        <NuxtLink to="/">Главная</NuxtLink
        ><span aria-hidden="true" class="product-crumbs__separator">/</span
        ><NuxtLink
          :to="{ path: '/catalog', query: { category: product.category.slug } }"
          >{{ product.category.name }}</NuxtLink
        ><span aria-hidden="true" class="product-crumbs__separator">/</span
        ><span aria-current="page">{{ product.name }}</span>
      </nav>
      <div class="product-layout">
        <ProductGallery
          :key="product.id"
          :name="product.name"
          :images="product.images"
        /><ProductPurchase
          :product="product"
          :phone="phone"
          :phone-href="phoneHref"
          @details="tab = $event"
        />
      </div>
    </div>
    <ProductInfoTabs
      v-model="tab"
      :product="product"
      :phone="phone"
      :phone-href="phoneHref"
    />
    <ProductCollections :product="product" />
  </div>
</template>
<style scoped>
.product-crumbs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding-top: 26px;
  color: var(--color-muted);
  font-size: 11px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.product-crumbs__separator {
  flex: none;
}
.product-crumbs a:hover,
.product-crumbs [aria-current] {
  color: var(--color-ink);
}
.product-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.12fr) minmax(0, 1fr);
  gap: 64px;
  align-items: start;
  padding-block: 32px 76px;
}
@media (max-width: 1180px) {
  .product-layout {
    gap: 42px;
  }
}
@media (max-width: 1024px) {
  .product-layout {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 34px;
  }
}
@media (max-width: 760px) {
  .product-crumbs {
    padding-top: 20px;
    font-size: 10px;
    gap: 6px;
  }
  .product-layout {
    display: flex;
    flex-direction: column;
    gap: 34px;
    padding-block: 24px 46px;
  }
  .product-layout > :last-child {
    width: 100%;
  }
}
</style>
