<script setup lang="ts">
import { setResponseStatus } from 'h3'
import PageIntro from '~/components/shared/PageIntro.vue'
import PageState from '~/components/shared/PageState.vue'
import CatalogPageBody from '~/features/catalog/components/CatalogPageBody.vue'
import { fetchPublicCollection } from '~/features/collections/services/collections'

definePageMeta({ key: (route) => route.path })
const route = useRoute()
const brand = computed(() => String(route.params.brand || ''))
const slug = computed(() => String(route.params.slug || ''))
const { requestBase, publicBase } = usePublicApiConfig()
const {
  data: collection,
  status,
  error,
  refresh,
} = await useAsyncData(
  () => `collection-profile:${brand.value}/${slug.value}`,
  (_app, { signal }) =>
    fetchPublicCollection(
      brand.value,
      slug.value,
      requestBase,
      publicBase,
      signal,
    ),
)
const missing = computed(() => error.value?.statusCode === 404)
const event = useRequestEvent()
if (event && error.value) setResponseStatus(event, missing.value ? 404 : 503)
useSeoMeta({
  title: () =>
    !collection.value
      ? `${missing.value ? 'Коллекция не найдена' : 'Коллекция'} — AgatCeramic`
      : undefined,
  robots: () =>
    error.value || !collection.value ? 'noindex,follow' : undefined,
})
</script>

<template>
  <CatalogPageBody
    v-if="collection && !error && status === 'success'"
    :collection="collection"
  />
  <template v-else>
    <PageIntro title="Коллекция" slug="collection" />
    <section class="section">
      <div class="container">
        <PageState
          v-if="error"
          :title="
            missing ? 'Коллекция не найдена' : 'Не удалось загрузить коллекцию'
          "
          :message="
            missing
              ? 'Возможно, коллекция недоступна или адрес изменился.'
              : 'Попробуйте ещё раз позже.'
          "
          :error="!missing"
          @retry="refresh()"
        />
        <PageState
          v-else
          title="Коллекция"
          message="Загружаем информацию о коллекции…"
          loading
        />
        <NuxtLink v-if="missing" to="/catalog">Весь каталог</NuxtLink>
      </div>
    </section>
  </template>
</template>
