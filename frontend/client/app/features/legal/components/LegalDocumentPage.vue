<script setup lang="ts">
import { setResponseStatus } from 'h3'
import PageState from '~/components/shared/PageState.vue'
import UiFormattedText from '~/components/ui/UiFormattedText.vue'
import { siteOrigin } from '#shared/utils/pageUrls'
import {
  fetchPublishedDocument,
  type LegalDocumentType,
} from '../services/legalDocuments'

const props = defineProps<{
  type: LegalDocumentType
  title: string
  path: string
}>()
const { requestBase } = usePublicApiConfig()
const {
  data: document,
  error,
  status,
  refresh,
} = await useAsyncData(`legal-document:${props.type}`, () =>
  fetchPublishedDocument(requestBase, props.type),
)
const event = useRequestEvent()
if (event && error.value)
  setResponseStatus(event, error.value.statusCode === 404 ? 404 : 503)
const canonical = new URL(
  props.path,
  siteOrigin(useRuntimeConfig().public.siteUrl || useRequestURL().origin),
).toString()
const description = `${props.title} — документы интернет-магазина AgatCeramic.`
useSeoMeta({
  title: `${props.title} — AgatCeramic`,
  description,
  ogTitle: props.title,
  ogDescription: description,
  ogType: 'website',
  ogUrl: canonical,
  robots: () => (error.value ? 'noindex, follow' : 'index, follow'),
})
useHead({ link: [{ rel: 'canonical', href: canonical }] })
</script>

<template>
  <article class="container legal-document">
    <NuxtLink to="/" class="eyebrow">AgatCeramic</NuxtLink>
    <h1>{{ title }}</h1>
    <UiFormattedText
      v-if="document"
      class="legal-document__body"
      :body="document.body"
      :content="document.content"
    />
    <PageState
      v-else
      :title="error ? 'Документ недоступен' : 'Загрузка документа'"
      :message="
        error
          ? 'Не удалось загрузить опубликованный документ. Попробуйте ещё раз позже.'
          : 'Загружаем документ…'
      "
      :error="!!error"
      :loading="status === 'pending'"
      @retry="refresh()"
    />
  </article>
</template>

<style scoped>
.legal-document {
  padding-block: clamp(60px, 8vw, 110px);
  overflow-wrap: anywhere;
}
h1 {
  max-width: 880px;
  margin: 18px 0 40px;
  font-size: clamp(38px, 4.6vw, 64px);
  font-weight: 500;
  line-height: 1.06;
  letter-spacing: -0.02em;
}
.legal-document__body {
  margin: 0;
}
</style>
