<script setup lang="ts">
import CatalogPageBody from '~/features/catalog/components/CatalogPageBody.vue'
import { fetchContentPage } from '~/services/contentPages'

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
