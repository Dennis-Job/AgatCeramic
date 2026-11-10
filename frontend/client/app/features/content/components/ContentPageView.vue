<script setup lang="ts">
import { setResponseStatus } from 'h3'
import ContentPageBody from './ContentPageBody.vue'
import PageState from '~/components/shared/PageState.vue'
import PageIntro from '~/components/shared/PageIntro.vue'
import { contentPagePath, siteOrigin } from '#shared/utils/pageUrls'

const props = defineProps<{
  slug: string
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
const origin = siteOrigin(
  useRuntimeConfig().public.siteUrl || useRequestURL().origin,
)
const canonical = new URL(contentPagePath(props.slug)!, origin).toString()
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
    content.value?.isIndexable === false ||
    (props.slug === 'catalog' &&
      ((route.query.page && route.query.page !== '1') ||
        route.query.category ||
        route.query.brand ||
        route.query.collection))
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
                  : props.slug === 'catalog'
                    ? 'CollectionPage'
                    : 'WebPage',
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
  <PageIntro v-if="!content" :title="fallbackTitle" :slug="slug" />
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
