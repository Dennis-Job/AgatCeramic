<script setup lang="ts">
const props = defineProps<{
  title: string
  description?: string | null
  caption?: string
  image?: { url: string; alt: string }
  imageFit?: 'contain'
  standalone?: boolean
}>()

const headingId = useId()
const imageElement = useTemplateRef<HTMLImageElement>('imageElement')
const failedImage = ref(false)
const hasImage = computed(() => !!props.image?.url && !failedImage.value)
const hasDescription = computed(() => !!props.description?.trim())

watch(
  () => props.image?.url,
  () => {
    failedImage.value = false
  },
)
// A cached or failed SSR image can finish before hydration attaches @error.
watch(imageElement, (image) => {
  if (image?.complete && image.naturalWidth === 0) failedImage.value = true
})
</script>

<template>
  <section
    v-if="standalone || (hasDescription && hasImage)"
    class="split-banner"
    :class="{ 'split-banner--without-image': !hasImage }"
    :aria-labelledby="headingId"
  >
    <div class="split-banner__text">
      <p v-if="$slots.eyebrow" class="split-banner__eyebrow eyebrow">
        <slot name="eyebrow" />
      </p>
      <component
        :is="standalone ? 'h1' : 'h2'"
        :id="headingId"
        class="split-banner__heading"
        >{{ title }}</component
      >
      <p v-if="caption" class="split-banner__caption">{{ caption }}</p>
      <p v-if="hasDescription" class="split-banner__description">
        {{ description }}
      </p>
      <slot name="action" />
    </div>
    <div v-if="image && hasImage" class="split-banner__media">
      <img
        ref="imageElement"
        :key="image.url"
        :class="{ 'split-banner__image--contain': imageFit === 'contain' }"
        :src="image.url"
        :alt="image.alt"
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
.split-banner {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
  background: var(--color-beige);
}
.split-banner__text {
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
.split-banner--without-image {
  grid-template-columns: minmax(0, 1fr);
}
.split-banner__heading {
  margin: 14px 0 18px;
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 500;
  line-height: 1.25;
  letter-spacing: -0.02em;
  text-wrap: balance;
}
.split-banner__eyebrow {
  color: var(--color-ink-soft);
}
.split-banner__caption {
  margin-bottom: 20px;
  color: var(--color-ink-soft);
  font-size: 12px;
  line-height: 1.7;
}
.split-banner__description {
  max-width: 440px;
  margin-bottom: 28px;
  color: var(--color-ink-soft);
  font-size: 14px;
  line-height: 1.9;
  white-space: pre-line;
}
.split-banner__media {
  position: relative;
  min-height: 480px;
  overflow: hidden;
}
.split-banner__media img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.split-banner__media .split-banner__image--contain {
  padding: var(--page-gutter);
  object-fit: contain;
}
@media (max-width: 900px) {
  .split-banner {
    grid-template-columns: minmax(0, 1fr);
  }
  .split-banner__text {
    padding: 48px var(--page-gutter);
  }
  .split-banner__media {
    min-height: 0;
    aspect-ratio: 6 / 5;
  }
}
</style>
