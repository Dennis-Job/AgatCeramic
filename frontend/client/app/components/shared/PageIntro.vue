<script setup lang="ts">
import type { PageIntroImage } from '~/types/contentPage'
import { defaultPageIntro } from '~/utils/pageIntro'

const props = defineProps<{
  title: string
  slug: string
  description?: string
  image?: PageIntroImage | null
}>()
const standard = computed(() => defaultPageIntro(props.slug, props.description))
const failed = ref<string[]>([])
const imageElement = ref<HTMLImageElement | null>(null)
const source = computed(() => {
  const custom = props.image?.imageUrl
  if (custom && !failed.value.includes(custom)) return custom
  return failed.value.includes(standard.value.imageUrl)
    ? ''
    : standard.value.imageUrl
})
const alt = computed(() =>
  source.value === props.image?.imageUrl
    ? props.image.imageAlt
    : standard.value.imageAlt,
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
  <section class="page-intro" aria-label="Вступление страницы">
    <img
      v-if="source"
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
    <div class="page-intro__veil" aria-hidden="true" />
    <div class="container page-intro__inner">
      <div class="page-intro__copy">
        <NuxtLink to="/" class="eyebrow">AgatCeramic</NuxtLink>
        <h1>{{ title }}</h1>
        <p v-if="standard.description">{{ standard.description }}</p>
      </div>
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
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background: linear-gradient(
    90deg,
    color-mix(in srgb, var(--color-bg) 94%, transparent) 0%,
    color-mix(in srgb, var(--color-bg) 88%, transparent) 28%,
    color-mix(in srgb, var(--color-bg) 53%, transparent) 49%,
    color-mix(in srgb, var(--color-bg) 8%, transparent) 77%,
    color-mix(in srgb, var(--color-bg) 2%, transparent) 100%
  );
}
.page-intro::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background: linear-gradient(to top, var(--color-bg) 0%, transparent 20%);
}
@media (min-width: 1900px) {
  .page-intro__inner {
    min-height: 460px;
  }
  .page-intro__veil {
    --intro-copy-end: calc(
      (100% - var(--content-width)) / 2 + var(--page-gutter) + 570px
    );
    background: linear-gradient(
      90deg,
      color-mix(in srgb, var(--color-bg) 98%, transparent) 0%,
      color-mix(in srgb, var(--color-bg) 98%, transparent) var(--intro-copy-end),
      color-mix(in srgb, var(--color-bg) 53%, transparent)
        calc(var(--intro-copy-end) + 240px),
      color-mix(in srgb, var(--color-bg) 8%, transparent)
        calc(var(--intro-copy-end) + 590px),
      color-mix(in srgb, var(--color-bg) 2%, transparent) 100%
    );
  }
}
@media (max-width: 760px) {
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
      color-mix(in srgb, var(--color-bg) 96%, transparent) 0%,
      color-mix(in srgb, var(--color-bg) 85%, transparent) 47%,
      color-mix(in srgb, var(--color-bg) 43%, transparent) 100%
    );
  }
}
</style>
