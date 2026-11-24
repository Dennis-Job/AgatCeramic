<script setup lang="ts">
import CollectionBanner from '~/features/collections/components/CollectionBanner.vue'
import { productCollectionLink } from '~/features/collections/services/collections'
import type { ProductDetail } from '../types/product'

defineProps<{ product: ProductDetail }>()
</script>

<template>
  <CollectionBanner
    v-if="product.collection"
    :collection="product.collection"
    :brand-name="product.brand?.name"
    :link="
      productCollectionLink(product.collection, product.brand?.page_available)
    "
  />
</template>
