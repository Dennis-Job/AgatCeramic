<script setup lang="ts">
import HomePage from '~/features/home/components/HomePage.vue'

const { data: content, status, error, refresh } = useHomePageContent()

const siteOrigin = useRuntimeConfig().public.siteUrl || useRequestURL().origin
const canonicalUrl = new URL('/', siteOrigin).toString()
const seo = computed(() => content.value?.seo)
const imageUrl = computed(() => {
  const image = seo.value?.ogImage || content.value?.heroSlides[0]?.image
  return image ? new URL(image, canonicalUrl).toString() : undefined
})

useSeoMeta({
  title: () => seo.value?.title || 'AgatCeramic',
  description: () => seo.value?.description || undefined,
  ogTitle: () => seo.value?.ogTitle || seo.value?.title || 'AgatCeramic',
  ogDescription: () =>
    seo.value?.ogDescription || seo.value?.description || undefined,
  ogImage: imageUrl,
  ogUrl: canonicalUrl,
  ogType: 'website',
  robots: () => (error.value ? 'noindex' : 'index,follow'),
})

useHead(() => ({
  link: [{ rel: 'canonical', href: canonicalUrl }],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        '@id': `${canonicalUrl}#website`,
        name: 'AgatCeramic',
        url: canonicalUrl,
        inLanguage: 'ru-RU',
        description: seo.value?.description || '',
      }).replace(/</g, '\\u003c'),
    },
  ],
}))
</script>

<template>
  <div
    v-if="
      content &&
      !content.blocks.some((block) => block.enabled && block.type === 'hero')
    "
    class="container home-unavailable"
  >
    <h1>{{ content.seo.title || 'AgatCeramic' }}</h1>
    <p v-if="!content.blocks.some((block) => block.enabled)" role="status">
      Для главной страницы пока нет опубликованного содержимого.
    </p>
  </div>
  <HomePage v-if="content" :content="content" />
  <section
    v-else
    class="home-unavailable container"
    :role="error ? 'alert' : 'status'"
  >
    <h1>
      {{ error ? 'Главная страница временно недоступна' : 'Главная страница' }}
    </h1>
    <p v-if="error">
      Не удалось загрузить содержимое. Попробуйте ещё раз позже.
    </p>
    <p v-else-if="status === 'pending'">Загружаем содержимое…</p>
    <p v-else>Для главной страницы пока нет опубликованного содержимого.</p>
    <button v-if="error" type="button" @click="refresh()">
      Повторить загрузку
    </button>
  </section>
</template>

<style scoped>
.home-unavailable {
  min-height: 60vh;
  padding-block: 100px;
}
.home-unavailable h1 {
  margin: 0 0 16px;
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 500;
}
.home-unavailable p {
  color: var(--color-muted);
  line-height: 1.7;
}
.home-unavailable button {
  min-height: 44px;
  margin-top: 16px;
  border: 1px solid var(--color-ink);
  padding: 8px 20px;
  background: var(--color-ink);
  color: var(--color-white);
}
</style>
