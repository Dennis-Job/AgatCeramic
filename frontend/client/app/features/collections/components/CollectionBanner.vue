<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'
import type { RouteLocationRaw } from 'vue-router'
import SplitBanner from '~/components/shared/SplitBanner.vue'
import type { ProductCollection } from '../types/collection'

const props = defineProps<{
  collection: ProductCollection
  brandName?: string
  standalone?: boolean
  link?: RouteLocationRaw
}>()
const title = computed(
  () => props.collection.headline?.trim() || props.collection.name,
)
const image = computed(() => {
  const { image_url, image_alt, name } = props.collection
  return image_url
    ? { url: image_url, alt: image_alt?.trim() || name }
    : undefined
})
const country = computed(() => {
  const code = props.collection.country_code
  if (!code || !/^[A-Z]{2}$/.test(code)) return ''
  return new Intl.DisplayNames(['ru'], { type: 'region' }).of(code) || ''
})
const caption = computed(() =>
  [props.brandName, country.value].filter(Boolean).join(' · '),
)
</script>

<template>
  <SplitBanner
    class="product-collection-story"
    :class="{ 'collection-banner': standalone }"
    :standalone="standalone"
    :title="title"
    :caption="caption"
    :description="collection.description"
    :image="image"
  >
    <template #eyebrow>{{ collection.name }}</template>
    <template v-if="link" #action>
      <NuxtLink :to="link" class="product-collection-story__link"
        >Товары коллекции <ArrowRight :size="16" aria-hidden="true"
      /></NuxtLink>
    </template>
  </SplitBanner>
</template>

<style scoped>
.product-collection-story__link {
  display: inline-flex;
  align-items: center;
  align-self: start;
  gap: 10px;
  max-width: 100%;
  min-height: 44px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--color-ink);
  font-size: 11px;
  font-weight: 500;
  line-height: 1.5;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  transition: gap 0.3s;
}
.product-collection-story__link svg {
  flex: none;
}
.product-collection-story__link:hover {
  gap: 16px;
}
</style>
