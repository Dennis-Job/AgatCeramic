<script setup lang="ts">
import type { PageIntroImage } from '~/types/contentPage'
import { defaultPageIntro } from '~/utils/pageIntro'

const props = defineProps<{
  title: string
  slug: string
  description?: string
  image?: PageIntroImage | null
  variant?: 'brand'
}>()
const standard = computed(() => defaultPageIntro(props.slug, props.description))
const failed = ref<string[]>([])
const imageElement = ref<HTMLImageElement | null>(null)
const source = computed(() => {
  const custom = props.image?.imageUrl
  if (custom && !failed.value.includes(custom)) return custom
  if (props.variant === 'brand') return ''
  return failed.value.includes(standard.value.imageUrl)
    ? ''
    : standard.value.imageUrl
})
const alt = computed(() =>
  source.value === props.image?.imageUrl
    ? props.image.imageAlt
    : standard.value.imageAlt,
)
const description = computed(() =>
  props.variant === 'brand' ? props.description : standard.value.description,
)
watch(
  () => [props.slug, props.image?.imageUrl],
  () => {
    failed.value = []
  },
)
function onImageError(event?: Event) {
  const target = event?.target as HTMLImageElement | undefined
  if (target && target.getAttribute('src') !== source.value) return
  if (source.value) failed.value = [...failed.value, source.value]
}
function checkCachedImage() {
  if (imageElement.value?.complete && !imageElement.value.naturalWidth)
    onImageError()
}
// An SSR image can fail before hydration attaches the error listener.
onMounted(checkCachedImage)
watch(source, async () => {
  await nextTick()
  checkCachedImage()
})
</script>

<template>
  <section
    class="page-intro"
    :class="{ 'page-intro--brand': variant === 'brand' }"
    aria-label="Вступление страницы"
  >
    <img
      v-if="source && variant !== 'brand'"
      ref="imageElement"
      :key="source"
      class="page-intro__image"
      :src="source"
      :alt="alt"
      width="2400"
      height="800"
      fetchpriority="high"
      decoding="async"
      @error="onImageError"
    />
    <div
      v-if="variant !== 'brand'"
      class="page-intro__veil"
      aria-hidden="true"
    />
    <div class="container page-intro__inner">
      <div class="page-intro__copy">
        <NuxtLink to="/" class="eyebrow">AgatCeramic</NuxtLink>
        <h1>{{ title }}</h1>
        <p v-if="description">{{ description }}</p>
      </div>
      <img
        v-if="source && variant === 'brand'"
        ref="imageElement"
        :key="source"
        class="page-intro__brand-image"
        :src="source"
        :alt="alt"
        width="600"
        height="400"
        fetchpriority="high"
        decoding="async"
        @error="onImageError"
      />
    </div>
  </section>
</template>

<style scoped>
.page-intro {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  overflow-wrap: anywhere;
  background: var(--color-bg);
}
.page-intro__inner {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  min-height: clamp(340px, 31vw, 440px);
}
.page-intro--brand .page-intro__inner {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
  gap: 48px;
}
.page-intro--brand .page-intro__copy {
  width: 100%;
}
.page-intro--brand p {
  max-width: 570px;
  white-space: pre-line;
}
.page-intro__brand-image {
  width: 100%;
  height: 280px;
  min-width: 0;
  object-fit: contain;
  align-self: center;
}
.page-intro__copy {
  width: min(100%, 570px);
  min-width: 0;
  padding-block: 64px 80px;
}
h1 {
  margin: 22px 0 26px;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  line-height: 1.06;
  letter-spacing: -0.02em;
}
p {
  max-width: 420px;
  margin: 0;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.8;
}
.page-intro__image {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 70% 50%;
}
.page-intro__veil {
  --intro-veil-fade-start: calc(
    max(0px, (100% - var(--content-width)) / 2) + clamp(310px, 26vw, 460px)
  );
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background: linear-gradient(
    90deg,
    color-mix(in srgb, var(--color-white) 94%, transparent) 0%,
    color-mix(in srgb, var(--color-white) 89%, transparent)
      calc(var(--intro-veil-fade-start) - 170px),
    color-mix(in srgb, var(--color-white) 79%, transparent)
      var(--intro-veil-fade-start),
    color-mix(in srgb, var(--color-white) 25%, transparent)
      calc(var(--intro-veil-fade-start) + 166px),
    transparent calc(var(--intro-veil-fade-start) + 360px)
  );
}
@media (min-width: 1900px) {
  .page-intro__inner {
    min-height: 460px;
  }
}
@media (max-width: 760px) {
  .page-intro--brand .page-intro__inner {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
  }
  .page-intro--brand .page-intro__copy {
    max-width: none;
    padding-block: 40px 24px;
  }
  .page-intro__brand-image {
    height: 180px;
    margin-bottom: 32px;
  }
  .page-intro__inner {
    min-height: 330px;
    align-items: flex-start;
  }
  .page-intro__copy {
    max-width: 450px;
    padding-block: 52px 70px;
  }
  h1 {
    margin-block: 20px;
  }
  p {
    max-width: 240px;
    font-size: 14px;
  }
  .page-intro__image {
    object-position: 76% center;
  }
  .page-intro__veil {
    background: linear-gradient(
      90deg,
      color-mix(in srgb, var(--color-white) 96%, transparent) 0%,
      color-mix(in srgb, var(--color-white) 94%, transparent) 52%,
      color-mix(in srgb, var(--color-white) 83%, transparent) 72%,
      transparent 100%
    );
  }
}
</style>
