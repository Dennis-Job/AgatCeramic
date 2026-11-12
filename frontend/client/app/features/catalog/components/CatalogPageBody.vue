<script setup lang="ts">
import { setResponseStatus } from 'h3'
import PageIntro from '~/components/shared/PageIntro.vue'
import PageState from '~/components/shared/PageState.vue'
import UiButton from '~/components/ui/UiButton.vue'
import UiDialog from '~/components/ui/UiDialog.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import ProductCardList from '~/features/product/components/ProductCardList.vue'
import CatalogCategories from './CatalogCategories.vue'
import CatalogFilterForm from './CatalogFilterForm.vue'
import { useCatalog } from '../composables/useCatalog'
import type { ContentPage } from '~/types/contentPage'
import { siteOrigin } from '#shared/utils/pageUrls'
import { formatMoney } from '~/utils/formatMoney'

const props = defineProps<{ content?: ContentPage | null; preview?: boolean }>()
const route = useRoute()
const {
  data: catalog,
  status,
  error,
  refresh,
  query,
  draft,
  collections,
  apply,
  reset,
  remove,
  sort,
  pageLink,
  ready,
} = useCatalog()
await ready
const event = useRequestEvent()
if (event && error.value && !props.preview) setResponseStatus(event, 503)
const drawer = ref(false)
const filters = computed(
  () =>
    catalog.value?.filters || { brands: [], collections: [], attributes: [] },
)
const sorting = computed({
  get: () => query.value.sort || 'name_asc',
  set: (value) => {
    void sort(value)
  },
})
const sortOptions = [
  { value: 'name_asc', label: 'Название А–Я' },
  { value: 'name_desc', label: 'Название Я–А' },
  { value: 'price_asc', label: 'Цена по возрастанию' },
  { value: 'price_desc', label: 'Цена по убыванию' },
  { value: 'newest', label: 'Сначала новые' },
]
const chips = computed(() =>
  Object.entries(query.value).flatMap(([key, value]) => {
    if (key === 'page' || key === 'sort') return []
    if (key === 'attributes') {
      try {
        const attributes = JSON.parse(value) as Record<string, unknown>
        return Object.entries(attributes).map(([id, raw]) => {
          const attribute = filters.value.attributes.find(
            (attribute) => String(attribute.id) === id,
          )
          const filter =
            raw && typeof raw === 'object' && !Array.isArray(raw)
              ? (raw as Record<string, unknown>)
              : {}
          const values = Array.isArray(filter.values)
            ? filter.values
                .filter((value) => typeof value === 'string')
                .map(
                  (value) =>
                    attribute?.options.find((option) => option.value === value)
                      ?.label ||
                    (attribute?.type === 'boolean'
                      ? value === 'true'
                        ? 'Да'
                        : 'Нет'
                      : value),
                )
                .join(', ')
            : ''
          const bounds = [
            typeof filter.min === 'string' && filter.min
              ? 'от ' + filter.min
              : '',
            typeof filter.max === 'string' && filter.max
              ? 'до ' + filter.max
              : '',
          ]
            .filter(Boolean)
            .join(' ')
          const detail =
            values ||
            (bounds
              ? bounds + (attribute?.unit ? ' ' + attribute.unit : '')
              : '') ||
            (typeof filter.text === 'string' ? filter.text : '')
          return {
            key,
            id,
            label:
              (attribute?.name || 'Характеристика ' + id) +
              (detail ? ': ' + detail : ''),
          }
        })
      } catch {
        return [{ key, id: '', label: 'Характеристики' }]
      }
    }
    const label =
      key === 'category'
        ? catalog.value?.categories.find((item) => item.slug === value)?.name ||
          value
        : key === 'brand'
          ? filters.value.brands.find((item) => item.slug === value)?.name ||
            value
          : key === 'in_stock'
            ? 'В наличии'
            : key === 'price_from'
              ? 'Цена от ' + formatMoney(value)
              : key === 'price_to'
                ? 'Цена до ' + formatMoney(value)
                : value
    return [{ key, id: '', label }]
  }),
)
async function commit(close?: () => void) {
  await apply()
  close?.()
}
async function clear(close?: () => void) {
  await reset()
  close?.()
}
if (!props.preview) {
  const origin = siteOrigin(
    useRuntimeConfig().public.siteUrl || useRequestURL().origin,
  )
  const canonical = new URL('/catalog', origin).toString()
  const seo = computed(() => props.content?.seo)
  useSeoMeta({
    title: () => seo.value?.title || 'Каталог — AgatCeramic',
    description: () =>
      seo.value?.description ||
      'Каталог плитки, керамогранита, мозаики и материалов для ремонта.',
    ogTitle: () =>
      seo.value?.ogTitle || seo.value?.title || 'Каталог — AgatCeramic',
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
      props.content?.isIndexable === false ||
      Object.entries(query.value).some(([key, value]) =>
        key === 'page'
          ? value !== '1'
          : key === 'sort'
            ? value !== 'name_asc'
            : !!value,
      )
        ? 'noindex,follow'
        : 'index,follow',
  })
  useHead(() => ({
    link: [{ rel: 'canonical', href: canonical }],
    script: [
      {
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'CollectionPage',
          name: props.content?.title || 'Каталог',
          url: canonical,
          inLanguage: 'ru-RU',
          description: seo.value?.description || '',
        }).replace(/</g, '\\u003c'),
      },
    ],
  }))
}
</script>

<template>
  <PageIntro
    :title="content?.title || 'Каталог'"
    slug="catalog"
    :description="content?.seo.description"
    :image="content?.intro"
  />
  <section id="catalog-products" class="section standalone-catalog">
    <div class="container catalog-layout">
      <aside class="catalog-sidebar" aria-label="Фильтры каталога">
        <CatalogCategories
          :categories="catalog?.categories || []"
          :query="query"
          :path="route.path"
        />
        <h2 class="catalog-sidebar__title">Фильтры</h2>
        <CatalogFilterForm
          v-model="draft"
          :filters="filters"
          :collections="collections"
          :busy="status === 'pending'"
          @apply="commit()"
          @reset="clear()"
        />
      </aside>
      <div class="catalog-results" :aria-busy="status === 'pending'">
        <div class="catalog-toolbar">
          <p role="status">Товаров: {{ catalog?.meta.total ?? '—' }}</p>
          <UiButton
            class="catalog-mobile-toggle"
            variant="outline"
            size="compact"
            @click="drawer = true"
            >Фильтры</UiButton
          >
          <UiSelect
            v-model="sorting"
            label="Сортировка"
            :options="sortOptions"
          />
        </div>
        <div
          v-if="chips.length"
          class="catalog-applied"
          aria-label="Применённые фильтры"
        >
          <UiButton
            v-for="chip in chips"
            :key="chip.key + chip.id"
            size="compact"
            variant="outline"
            :aria-label="'Убрать фильтр: ' + chip.label"
            @click="remove(chip.key, chip.id)"
            >{{ chip.label }} ×</UiButton
          >
          <UiButton size="compact" variant="outline" @click="clear()"
            >Сбросить всё</UiButton
          >
        </div>
        <PageState
          v-if="error"
          title="Не удалось загрузить каталог"
          :message="
            error.statusCode === 422
              ? 'Проверьте фильтры: ссылка содержит недоступные или неверные значения. Сбросьте фильтры и попробуйте снова.'
              : 'Попробуйте ещё раз позже.'
          "
          error
          @retry="refresh()"
        />
        <PageState
          v-else-if="status === 'pending'"
          title="Товары"
          message="Загружаем каталог…"
          loading
        />
        <template v-else>
          <ProductCardList
            v-if="catalog?.data.length"
            :products="catalog.data"
            class="products"
          />
          <PageState
            v-else
            title="По вашему запросу ничего не найдено"
            message="Измените параметры или сбросьте фильтры."
          />
          <nav
            v-if="catalog && catalog.meta.last_page > 1"
            class="catalog-pagination"
            aria-label="Страницы каталога"
          >
            <NuxtLink
              v-if="catalog.meta.current_page > 1"
              :to="pageLink(catalog.meta.current_page - 1)"
              >← Предыдущая</NuxtLink
            >
            <span aria-current="page"
              >{{ catalog.meta.current_page }} /
              {{ catalog.meta.last_page }}</span
            >
            <NuxtLink
              v-if="catalog.meta.current_page < catalog.meta.last_page"
              :to="pageLink(catalog.meta.current_page + 1)"
              >Следующая →</NuxtLink
            >
          </nav>
        </template>
      </div>
    </div>
    <UiDialog
      v-if="drawer"
      v-slot="{ close }"
      title="Фильтры каталога"
      size="drawer"
      @close="drawer = false"
    >
      <div class="catalog-drawer-body">
        <CatalogCategories
          :categories="catalog?.categories || []"
          :query="query"
          :path="route.path"
          @navigate="close()"
        />
        <CatalogFilterForm
          v-model="draft"
          :filters="filters"
          :collections="collections"
          :busy="status === 'pending'"
          @apply="commit(close)"
          @reset="clear(close)"
        />
      </div>
    </UiDialog>
  </section>
</template>

<style scoped>
.standalone-catalog {
  scroll-margin-top: 100px;
}
.catalog-layout {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  gap: 40px;
  align-items: start;
}
.catalog-sidebar,
.catalog-results {
  min-width: 0;
}
.catalog-sidebar__title {
  margin: 32px 0 24px;
  border-top: 1px solid var(--color-line);
  padding-top: 24px;
  font-size: 14px;
  font-weight: 500;
}
.catalog-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 20px;
  margin-bottom: 32px;
  border-bottom: 1px solid var(--color-line);
  padding-bottom: 24px;
}
.catalog-toolbar p {
  margin: 0;
  padding-bottom: 14px;
  font-size: 13px;
  color: var(--color-muted);
}
.catalog-toolbar .ui-select {
  width: min(260px, 100%);
}
.catalog-toolbar .catalog-mobile-toggle {
  display: none;
}
.catalog-applied {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 32px;
}
.catalog-applied :deep(.ui-button) {
  max-width: 100%;
  white-space: normal;
  overflow-wrap: anywhere;
}
.catalog-pagination {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 24px;
  margin-top: 48px;
  font-size: 13px;
}
.catalog-pagination a {
  min-height: 44px;
  border-bottom: 1px solid var(--color-line);
  padding-block: 14px;
}
.catalog-drawer-body {
  overflow-y: auto;
  padding: 24px var(--ui-drawer-gutter);
}
.catalog-drawer-body .catalog-filter-form {
  margin-top: 32px;
}
@media (max-width: 1023px) {
  .catalog-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .catalog-sidebar {
    display: none;
  }
  .catalog-toolbar .catalog-mobile-toggle {
    display: inline-flex;
  }
  .catalog-toolbar {
    flex-wrap: wrap;
    align-items: center;
  }
  .catalog-toolbar p {
    padding: 0;
  }
  .catalog-toolbar .ui-select {
    flex-basis: 100%;
    width: 100%;
  }
}
</style>
