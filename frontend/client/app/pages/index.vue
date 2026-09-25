<script setup lang="ts">
import HomePage from '~/features/home/components/HomePage.vue'

const siteOrigin = useRuntimeConfig().public.siteUrl || useRequestURL().origin
const canonicalUrl = new URL('/', siteOrigin).toString()
const imageUrl = new URL(
  '/images/home/hero-porcelain.webp',
  canonicalUrl,
).toString()

useSeoMeta({
  title: 'AgatCeramic — керамогранит, плитка и мозаика',
  description:
    'Откройте керамогранит, керамическую плитку и мозаику AgatCeramic. Изучайте фактуры, форматы и оттенки для своего пространства.',
  ogTitle: 'AgatCeramic — керамогранит, плитка и мозаика',
  ogDescription: 'Материалы, которые задают характер пространству.',
  ogImage: imageUrl,
  ogUrl: canonicalUrl,
  ogType: 'website',
})

useHead({
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
        description: 'Керамогранит, керамическая плитка и мозаика AgatCeramic.',
      }),
    },
  ],
})
</script>

<template>
  <HomePage />
</template>
