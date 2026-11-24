<script setup lang="ts">
import { setResponseStatus } from 'h3'
import CatalogPageBody from '~/features/catalog/components/CatalogPageBody.vue'
import BrandBanner from '~/features/brands/components/BrandBanner.vue'
import PageState from '~/components/shared/PageState.vue'
import { fetchPublicBrand } from '~/features/brands/services/brands'

definePageMeta({ key: (route) => route.path })
const route = useRoute()
const slug = computed(() => String(route.params.slug || ''))
const { requestBase, publicBase } = usePublicApiConfig()
const {
  data: brand,
  status,
  error,
  refresh,
} = await useAsyncData(
  () => 'brand-profile:' + slug.value,
  (_app, { signal }) =>
    fetchPublicBrand(slug.value, requestBase, publicBase, signal),
)
const missing = computed(() => error.value?.statusCode === 404)
const event = useRequestEvent()
if (event && error.value) setResponseStatus(event, missing.value ? 404 : 503)
useSeoMeta({
  title: () =>
    !brand.value
      ? missing.value
        ? 'Бренд не найден — AgatCeramic'
        : 'Бренд — AgatCeramic'
      : undefined,
  robots: () => (error.value || !brand.value ? 'noindex,follow' : undefined),
})
</script>

<template>
  <CatalogPageBody
    v-if="brand && !error && status === 'success'"
    :brand="brand"
  />
  <template v-else>
    <BrandBanner />
    <section class="section">
      <div class="container">
        <PageState
          v-if="error"
          :title="missing ? 'Бренд не найден' : 'Не удалось загрузить бренд'"
          :message="
            missing
              ? 'Возможно, страница недоступна или адрес изменился. Перейдите в каталог.'
              : 'Попробуйте загрузить страницу ещё раз.'
          "
          :error="!missing"
          @retry="refresh()"
        />
        <PageState
          v-else
          title="Бренд"
          message="Загружаем информацию о бренде…"
          loading
        />
        <NuxtLink v-if="missing" to="/catalog">Весь каталог</NuxtLink>
      </div>
    </section>
  </template>
</template>
