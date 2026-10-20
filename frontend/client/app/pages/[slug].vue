<script setup lang="ts">
import ContentPageView from '~/features/content/components/ContentPageView.vue'
import { contentPagePath } from '#shared/utils/pageUrls'

definePageMeta({
  validate: (route) =>
    typeof route.params.slug === 'string' &&
    contentPagePath(route.params.slug) !== null,
})
const route = useRoute()
const slug = computed(() => String(route.params.slug))
if (slug.value === 'home') await navigateTo('/', { redirectCode: 301 })
</script>

<template>
  <ContentPageView
    v-if="slug !== 'home'"
    :key="slug"
    :slug="slug"
    fallback-title="Страница"
  />
</template>
