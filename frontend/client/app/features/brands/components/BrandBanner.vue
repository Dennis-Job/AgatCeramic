<script setup lang="ts">
import SplitBanner from '~/components/shared/SplitBanner.vue'
import type { PublicBrand } from '../types/brand'

defineProps<{ brand?: PublicBrand }>()
</script>

<template>
  <SplitBanner
    :title="brand?.name || 'Бренд'"
    :description="brand?.description"
    :image="
      brand?.logoUrl ? { url: brand.logoUrl, alt: brand.logoAlt } : undefined
    "
    image-fit="contain"
    standalone
  >
    <template #eyebrow><NuxtLink to="/">AgatCeramic</NuxtLink></template>
  </SplitBanner>
</template>
