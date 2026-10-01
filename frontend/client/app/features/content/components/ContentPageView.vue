<script setup lang="ts">
import { setResponseStatus } from 'h3'
import ContentPageBody from './ContentPageBody.vue'
import PageState from '~/components/shared/PageState.vue'

const props = defineProps<{
  slug: 'contacts' | 'about' | 'catalog'
  fallbackTitle: string
}>()
const {
  data: content,
  status,
  error,
  refresh,
} = await useContentPage(props.slug)
const missing = computed(() => error.value?.statusCode === 404)
const event = useRequestEvent()
if (event && error.value) setResponseStatus(event, missing.value ? 404 : 503)

const route = useRoute()
const origin = useRuntimeConfig().public.siteUrl || useRequestURL().origin
const canonical = new URL(`/${props.slug}`, origin).toString()
const seo = computed(() => content.value?.seo)
useSeoMeta({
  title: () => seo.value?.title || `${props.fallbackTitle} — AgatCeramic`,
  description: () => seo.value?.description || undefined,
  ogTitle: () => seo.value?.ogTitle || seo.value?.title || props.fallbackTitle,
  ogDescription: () =>
    seo.value?.ogDescription || seo.value?.description || undefined,
  ogImage: () =>
    seo.value?.ogImage
      ? new URL(seo.value.ogImage, canonical).toString()
      : undefined,
  ogUrl: canonical,
  ogType: 'website',
  robots: () =>
    error.value ||
    (props.slug === 'catalog' && route.query.page && route.query.page !== '1')
      ? 'noindex,follow'
      : 'index,follow',
})
useHead(() => ({
  link: [{ rel: 'canonical', href: canonical }],
  script: content.value
    ? [
        {
          type: 'application/ld+json',
          innerHTML: JSON.stringify({
            '@context': 'https://schema.org',
            '@type':
              props.slug === 'contacts'
                ? 'ContactPage'
                : props.slug === 'about'
                  ? 'AboutPage'
                  : 'CollectionPage',
            name: content.value.title,
            description: seo.value?.description || '',
            url: canonical,
            inLanguage: 'ru-RU',
          }).replace(/</g, '\\u003c'),
        },
      ]
    : [],
}))
</script>

<template>
  <div v-if="!content" class="container page-intro">
    <NuxtLink to="/" class="eyebrow">AgatCeramic</NuxtLink>
    <h1>{{ fallbackTitle }}</h1>
  </div>
  <ContentPageBody v-if="content" :content="content" />
  <div v-else class="container">
    <PageState
      :title="
        missing
          ? 'Страница не опубликована'
          : error
            ? 'Страница временно недоступна'
            : 'Загрузка страницы'
      "
      :message="
        missing
          ? 'Содержимое этой страницы пока недоступно.'
          : error
            ? 'Не удалось загрузить содержимое. Попробуйте ещё раз позже.'
            : status === 'pending'
              ? 'Загружаем содержимое…'
              : 'Содержимое пока не опубликовано.'
      "
      :error="!!error && !missing"
      :loading="status === 'pending'"
      @retry="refresh()"
    />
  </div>
</template>

<style scoped>
.page-intro {
  padding-block: clamp(60px, 8vw, 110px);
  overflow-wrap: anywhere;
}
h1 {
  max-width: 880px;
  margin: 18px 0 24px;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  line-height: 1.06;
  letter-spacing: -0.02em;
}
p {
  max-width: 680px;
  margin: 0;
  color: var(--color-muted);
  font-size: 15px;
  line-height: 1.8;
}
</style>
