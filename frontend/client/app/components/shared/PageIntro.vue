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
    <div class="container page-intro__inner">
      <div class="page-intro__copy">
        <NuxtLink to="/" class="eyebrow">AgatCeramic</NuxtLink>
        <h1>{{ title }}</h1>
        <p v-if="standard.description">{{ standard.description }}</p>
      </div>
      <div class="page-intro__art">
        <img
          v-if="source"
          ref="imageElement"
          :key="source"
          class="page-intro__image"
          :class="{
            'page-intro__image--standard': source === standard.imageUrl,
          }"
          :src="source"
          :alt="alt"
          width="1200"
          height="800"
          fetchpriority="high"
          decoding="async"
          @error="onImageError"
        />
      </div>
    </div>
  </section>
</template>

<style scoped>
.page-intro {
  overflow-wrap: anywhere;
}
.page-intro__inner {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  align-items: center;
  min-height: clamp(300px, 31vw, 410px);
}
.page-intro__copy {
  min-width: 0;
  padding-block: 54px 86px;
  padding-right: 20px;
}
h1 {
  margin: 22px 0 24px;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  line-height: 1.06;
  letter-spacing: -0.02em;
}
p {
  max-width: 440px;
  margin: 0;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.8;
}
.page-intro__art {
  min-width: 0;
  height: clamp(300px, 31vw, 410px);
  align-self: stretch;
  display: grid;
  align-items: center;
}
.page-intro__image {
  width: 100%;
  height: 100%;
  max-height: 410px;
  aspect-ratio: 3 / 2;
  object-fit: contain;
}
.page-intro__image--standard {
  mask-image:
    linear-gradient(to right, transparent, #000 7%, #000 93%, transparent),
    linear-gradient(to bottom, transparent, #000 6%, #000 94%, transparent);
  mask-composite: intersect;
}
@media (max-width: 760px) {
  .page-intro__inner {
    grid-template-columns: minmax(0, 1fr);
    min-height: 0;
    padding-block: 0 20px;
  }
  .page-intro__copy {
    padding: 44px 0 18px;
  }
  h1 {
    margin-block: 18px;
  }
  .page-intro__image {
    height: auto;
    max-height: 270px;
  }
  .page-intro__art {
    height: auto;
    aspect-ratio: 3 / 2;
    max-height: 270px;
  }
}
</style>
