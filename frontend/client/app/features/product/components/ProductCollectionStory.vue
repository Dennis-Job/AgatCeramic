<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'
import type { ProductDetail } from '../types/product'

const props = defineProps<{ product: ProductDetail }>()
const failedImage = ref(false)
const collectionImage = useTemplateRef<HTMLImageElement>('collectionImage')
// A cached/fast failed SSR image can finish before hydration attaches @error.
onMounted(() => {
  const image = collectionImage.value
  if (image?.complete && image.naturalWidth === 0) failedImage.value = true
})
watch(
  () => props.product.collection?.image_url,
  () => {
    failedImage.value = false
  },
)
const collection = computed(() => props.product.collection)
const visible = computed(
  () =>
    !!collection.value?.description?.trim() &&
    !!collection.value.image_url &&
    !failedImage.value,
)
const title = computed(
  () => collection.value?.headline?.trim() || collection.value?.name,
)
const headingId = computed(() => 'product-collection-' + props.product.id)
const country = computed(() => {
  const code = collection.value?.country_code
  if (!code || !/^[A-Z]{2}$/.test(code)) return ''
  return new Intl.DisplayNames(['ru'], { type: 'region' }).of(code) || ''
})
const caption = computed(() =>
  [props.product.brand?.name, country.value].filter(Boolean).join(' · '),
)
const catalogLink = computed(() => ({
  path: '/catalog',
  query: {
    brand: collection.value?.brand_slug,
    collection: collection.value?.name,
  },
}))
</script>

<template>
  <section
    v-if="visible && collection"
    class="product-collection-story"
    :aria-labelledby="headingId"
  >
    <div class="product-collection-story__text">
      <p class="eyebrow">{{ collection.name }}</p>
      <h2 :id="headingId">{{ title }}</h2>
      <p v-if="caption" class="product-collection-story__caption">
        {{ caption }}
      </p>
      <p class="product-collection-story__description">
        {{ collection.description }}
      </p>
      <NuxtLink :to="catalogLink" class="product-collection-story__link"
        >Товары коллекции <ArrowRight :size="16" aria-hidden="true"
      /></NuxtLink>
    </div>
    <div class="product-collection-story__image">
      <img
        ref="collectionImage"
        :src="collection.image_url || undefined"
        :alt="collection.image_alt?.trim() || collection.name"
        width="960"
        height="800"
        sizes="(max-width: 900px) 100vw, 53vw"
        loading="lazy"
        decoding="async"
        @error="failedImage = true"
      />
    </div>
  </section>
</template>

<style scoped>
.product-collection-story {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
  background: var(--color-beige);
}
.product-collection-story__text {
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
  padding: clamp(48px, 6vw, 96px);
  padding-left: max(
    clamp(48px, 6vw, 96px),
    calc((100vw - var(--content-width)) / 2 + var(--page-gutter))
  );
  overflow-wrap: anywhere;
}
h2 {
  margin: 14px 0 18px;
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 500;
  line-height: 1.25;
  letter-spacing: -0.02em;
  text-wrap: balance;
}
.product-collection-story .eyebrow {
  color: var(--color-ink-soft);
}
.product-collection-story__caption {
  margin-bottom: 20px;
  color: var(--color-ink-soft);
  font-size: 12px;
  line-height: 1.7;
}
.product-collection-story__description {
  max-width: 440px;
  margin-bottom: 28px;
  color: var(--color-ink-soft);
  font-size: 14px;
  line-height: 1.9;
  white-space: pre-line;
}
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
.product-collection-story__image {
  position: relative;
  min-height: 480px;
  overflow: hidden;
}
.product-collection-story__image img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
@media (max-width: 900px) {
  .product-collection-story {
    grid-template-columns: minmax(0, 1fr);
  }
  .product-collection-story__text {
    padding: 48px var(--page-gutter);
  }
  .product-collection-story__image {
    min-height: 0;
    aspect-ratio: 6 / 5;
  }
}
</style>
