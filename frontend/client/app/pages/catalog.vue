<script setup lang="ts">
import CatalogPageBody from '~/features/catalog/components/CatalogPageBody.vue'
import { fetchContentPage } from '~/services/contentPages'
import { brandPath } from '~/features/brands/services/brands'

const route = useRoute()
if (typeof route.query.brand === 'string' && route.query.brand) {
  const query = { ...route.query }
  delete query.brand
  await navigateTo(
    { path: brandPath(route.query.brand), query, hash: route.hash },
    { redirectCode: 301 },
  )
}

const { requestBase, publicBase } = usePublicApiConfig()
const { data: content } = useAsyncData('catalog-decoration', () =>
  fetchContentPage('catalog', requestBase, publicBase, {
    timeout: 3000,
    retry: 0,
  }).catch(() => null),
)
</script>

<template>
  <CatalogPageBody :content="content" />
</template>
