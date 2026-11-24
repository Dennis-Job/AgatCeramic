<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'
import type { RouteLocationRaw } from 'vue-router'
import type { ProductCollection } from '../types/collection'

const props = defineProps<{
  collection: ProductCollection
  brandName?: string
  standalone?: boolean
  link?: RouteLocationRaw
}>()
const failedImage = ref(false)
const collectionImage = useTemplateRef<HTMLImageElement>('collectionImage')
// A cached/fast failed SSR image can finish before hydration attaches @error.
onMounted(() => {
  const image = collectionImage.value
  if (image?.complete && image.naturalWidth === 0) failedImage.value = true
})
watch(
  () => props.collection.image_url,
  () => {
    failedImage.value = false
  },
)
const hasImage = computed(
  () => !!props.collection.image_url && !failedImage.value,
)
const visible = computed(
  () =>
    props.standalone ||
    (!!props.collection.description?.trim() && hasImage.value),
)
const title = computed(
  () => props.collection.headline?.trim() || props.collection.name,
)
const headingId = useId()
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
  <section
    v-if="visible"
    class="product-collection-story"
    :class="{
      'collection-banner': standalone,
      'product-collection-story--without-image': !hasImage,
    }"
    :aria-labelledby="headingId"
  >
    <div class="product-collection-story__text">
      <p class="eyebrow">{{ collection.name }}</p>
      <component
        :is="standalone ? 'h1' : 'h2'"
        :id="headingId"
        class="product-collection-story__heading"
        >{{ title }}</component
      >
      <p v-if="caption" class="product-collection-story__caption">
        {{ caption }}
      </p>
      <p
        v-if="collection.description?.trim()"
        class="product-collection-story__description"
      >
        {{ collection.description }}
      </p>
      <NuxtLink v-if="link" :to="link" class="product-collection-story__link"
        >Товары коллекции <ArrowRight :size="16" aria-hidden="true"
      /></NuxtLink>
    </div>
    <div v-if="hasImage" class="product-collection-story__image">
      <img
        ref="collectionImage"
        :src="collection.image_url || undefined"
        :alt="collection.image_alt?.trim() || collection.name"
        width="960"
        height="800"
        sizes="(max-width: 900px) 100vw, 53vw"
        :loading="standalone ? 'eager' : 'lazy'"
        :fetchpriority="standalone ? 'high' : undefined"
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
.product-collection-story--without-image {
  grid-template-columns: minmax(0, 1fr);
}
.product-collection-story__heading {
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
