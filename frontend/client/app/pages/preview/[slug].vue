<script setup lang="ts">
import { useDraftPreview } from '~/features/preview/composables/useDraftPreview'
import ContentPageBody from '~/features/content/components/ContentPageBody.vue'
import HomePageBody from '~/features/home/components/HomePageBody.vue'
import SiteHeader from '~/components/shared/SiteHeader.vue'
import SiteFooter from '~/components/shared/SiteFooter.vue'
import PageState from '~/components/shared/PageState.vue'

definePageMeta({ layout: false })
const route = useRoute()
const slug = computed(() => String(route.params.slug))
const { data, loading, error, refresh } = useDraftPreview(slug)
useSeoMeta({
  title: 'Предпросмотр черновика — AgatCeramic',
  robots: 'noindex,nofollow,noarchive',
})
useHead({ meta: [{ name: 'referrer', content: 'no-referrer' }] })
</script>

<template>
  <template v-if="data">
    <SiteHeader
      :content="data.appearance.header"
      :tagline="data.appearance.footer.tagline"
      :current-path="data.page.slug === 'home' ? '/' : `/${data.page.slug}`"
      navigation-label="Навигация предпросмотра"
    />
    <main aria-label="Содержимое предпросмотра сайта">
      <HomePageBody v-if="data.page.slug === 'home'" :content="data.page" />
      <ContentPageBody v-else :content="data.page" />
    </main>
    <SiteFooter
      :content="data.appearance.footer"
      :navigation="data.appearance.header.navigation"
      :logo-url="data.appearance.header.logoUrl"
      :logo-alt="data.appearance.header.logoAlt"
    />
  </template>
  <main
    v-else
    class="container preview-state"
    aria-label="Состояние предпросмотра сайта"
  >
    <h1>Предпросмотр черновика</h1>
    <PageState
      :title="error ? 'Предпросмотр недоступен' : 'Загрузка черновика'"
      :message="error || 'Проверяем доступ к сохранённому черновику…'"
      :error="!!error"
      :loading="loading"
      @retry="refresh()"
    />
  </main>
</template>

<style scoped>
.preview-state {
  min-height: 60vh;
  padding-block: 100px;
  overflow-wrap: anywhere;
}
h1 {
  margin: 0 0 16px;
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 500;
}
</style>
