<script setup lang="ts">
import { setResponseStatus } from 'h3'
import PageIntro from '~/components/shared/PageIntro.vue'
import PageState from '~/components/shared/PageState.vue'
import UiButton from '~/components/ui/UiButton.vue'
import UiDialog from '~/components/ui/UiDialog.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import ProductCardList from '~/features/product/components/ProductCardList.vue'
import CatalogCategories from './CatalogCategories.vue'
import CatalogBrandNavigation from './CatalogBrandNavigation.vue'
import CatalogFilterForm from './CatalogFilterForm.vue'
import { useCatalog } from '../composables/useCatalog'
import { useCatalogLoadMore } from '../composables/useCatalogLoadMore'
import { useStickyCatalogNavigation } from '../composables/useStickyCatalogNavigation'
import type { ContentPage } from '~/types/contentPage'
import { siteOrigin } from '#shared/utils/pageUrls'
import { useCatalogAppliedFilters } from '../composables/useCatalogAppliedFilters'
import type { PublicBrand } from '~/features/brands/types/brand'
import { brandPath } from '~/features/brands/services/brands'

const props = defineProps<{
  content?: ContentPage | null
  preview?: boolean
  brand?: PublicBrand
}>()
const route = useRoute()
const router = useRouter()
const { navigation, headerHeight, pinned, revealFocusedControl } =
  useStickyCatalogNavigation()
const {
  data: responseCatalog,
  status,
  error,
  refresh,
  query,
  draft,
  collections,
  draftFilters,
  draftNavigation,
  draftFiltersPending,
  draftFiltersError,
  refreshDraftFilters,
  selectCategory,
  resetDraft,
  restoreDraft,
  apply,
  reset,
  resetFilters,
  remove,
  sort,
  pageLink,
  ready,
} = useCatalog()
await ready
const previousCatalog = shallowRef(responseCatalog.value)
watch(responseCatalog, (value) => {
  if (value) previousCatalog.value = value
})
const catalog = computed(() => responseCatalog.value || previousCatalog.value)
const more = useCatalogLoadMore(
  catalog,
  query,
  computed(() => status.value === 'pending'),
)
const {
  products,
  loading: loadingMore,
  error: moreError,
  hasMore,
  total: listingTotal,
  nextPage,
  nextCount,
} = more
const moreControl = ref<HTMLElement | null>(null)
const moreCounter = ref<HTMLElement | null>(null)
watch(
  hasMore,
  async (value) => {
    if (import.meta.server || value || !loadingMore.value) return
    const active = document.activeElement
    if (
      !value &&
      loadingMore.value &&
      active &&
      moreControl.value?.contains(active)
    ) {
      await nextTick()
      if (
        document.activeElement === document.body ||
        document.activeElement === active
      )
        moreCounter.value?.focus({ preventScroll: true })
    }
  },
  { flush: 'sync' },
)
function loadNext(event: MouseEvent) {
  if (
    event.button ||
    event.ctrlKey ||
    event.metaKey ||
    event.shiftKey ||
    event.altKey
  )
    return
  event.preventDefault()
  void more.loadMore()
}
const event = useRequestEvent()
if (event && error.value && !props.preview) setResponseStatus(event, 503)
const drawer = ref(false)
const scopeBrand = computed(() =>
  query.value.brand
    ? props.brand?.name ||
      catalog.value?.category_navigation?.brand?.name ||
      catalog.value?.brand_navigation.find(
        (brand) => brand.slug === query.value.brand,
      )?.name ||
      catalog.value?.filters.brands.find(
        (brand) => brand.slug === query.value.brand,
      )?.name ||
      query.value.brand
    : '',
)
const scopeCategories = computed(
  () =>
    catalog.value?.category_navigation?.categories ||
    catalog.value?.categories ||
    [],
)
const scopeTotal = computed(() => catalog.value?.category_navigation?.total)
const listingTitle = computed(
  () =>
    scopeCategories.value.find(
      (category) => category.slug === query.value.category,
    )?.name || 'Все товары',
)
const draftCategories = computed(
  () =>
    draftNavigation.value?.categories ||
    (draft.value.brand === (query.value.brand || '')
      ? scopeCategories.value
      : []),
)
const scoped = computed(() => !!(query.value.brand || query.value.category))
const catalogLink = computed(
  () =>
    `${props.preview ? route.path : '/catalog'}${query.value.sort ? '?sort=' + encodeURIComponent(query.value.sort) : ''}#catalog-products`,
)
function openFilters() {
  restoreDraft()
  drawer.value = true
}
function closeFilters() {
  drawer.value = false
  restoreDraft()
}
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
const appliedChips = useCatalogAppliedFilters(catalog, query)
const chips = computed(() =>
  appliedChips.value.filter((chip) => !props.brand || chip.key !== 'brand'),
)
async function commit(close?: () => void, automatic = false) {
  await apply(automatic)
  close?.()
}
async function clear(close?: () => void) {
  await reset()
  close?.()
}
async function clearFilters() {
  await resetFilters()
}
if (!props.preview) {
  const origin = siteOrigin(
    useRuntimeConfig().public.siteUrl || useRequestURL().origin,
  )
  const canonical = computed(() =>
    new URL(
      props.brand ? brandPath(props.brand.slug) : '/catalog',
      origin,
    ).toString(),
  )
  const seo = computed(() => props.content?.seo)
  const pageTitle = computed(() =>
    props.brand
      ? `${props.brand.name} — AgatCeramic`
      : seo.value?.title || 'Каталог — AgatCeramic',
  )
  const description = computed(() =>
    props.brand
      ? props.brand.description?.slice(0, 300) ||
        `Товары ${props.brand.name} в каталоге AgatCeramic.`
      : seo.value?.description ||
        'Каталог плитки, керамогранита, мозаики и материалов для ремонта.',
  )
  useSeoMeta({
    title: pageTitle,
    description,
    ogTitle: () =>
      props.brand ? pageTitle.value : seo.value?.ogTitle || pageTitle.value,
    ogDescription: () =>
      props.brand
        ? description.value
        : seo.value?.ogDescription || description.value,
    ogImage: () =>
      props.brand?.logoUrl
        ? new URL(props.brand.logoUrl, canonical.value).toString()
        : seo.value?.ogImage
          ? new URL(seo.value.ogImage, canonical.value).toString()
          : undefined,
    ogUrl: canonical,
    ogType: 'website',
    robots: () =>
      error.value ||
      props.content?.isIndexable === false ||
      Object.entries(query.value).some(([key, value]) =>
        key === 'brand' && props.brand
          ? false
          : key === 'page'
            ? value !== '1'
            : key === 'sort'
              ? value !== 'name_asc'
              : !!value,
      )
        ? 'noindex,follow'
        : 'index,follow',
  })
  useHead(() => ({
    link: [{ rel: 'canonical', href: canonical.value }],
    script: [
      {
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'CollectionPage',
          name: props.brand?.name || props.content?.title || 'Каталог',
          url: canonical.value,
          inLanguage: 'ru-RU',
          description: description.value,
          ...(props.brand
            ? {
                about: {
                  '@type': 'Brand',
                  name: props.brand.name,
                  url: canonical.value,
                  ...(props.brand.description
                    ? { description: props.brand.description }
                    : {}),
                  ...(props.brand.logoUrl
                    ? { logo: new URL(props.brand.logoUrl, origin).toString() }
                    : {}),
                },
              }
            : {}),
        }).replace(/</g, '\\u003c'),
      },
    ],
  }))
}
</script>

<template>
  <PageIntro
    :title="brand?.name || content?.title || 'Каталог'"
    :slug="brand ? 'brand' : 'catalog'"
    :variant="brand ? 'brand' : undefined"
    :description="brand ? brand.description || '' : content?.seo.description"
    :image="
      brand
        ? brand.logoUrl
          ? { imageUrl: brand.logoUrl, imageAlt: brand.logoAlt }
          : null
        : content?.intro
    "
  />
  <section
    id="catalog-products"
    class="section standalone-catalog"
    :style="{ '--catalog-header-height': `${headerHeight}px` }"
    @focusin="revealFocusedControl"
  >
    <div class="container">
      <CatalogBrandNavigation
        :brands="catalog?.brand_navigation || []"
        :path="route.path"
      />
      <div
        v-if="scopeCategories.length || query.brand"
        ref="navigation"
        class="catalog-category-navigation"
        :class="{ 'catalog-category-navigation--pinned': pinned }"
      >
        <CatalogCategories
          :categories="scopeCategories"
          :query="query"
          :path="route.path"
          :brand="scopeBrand"
          :total="scopeTotal"
        />
      </div>
      <div class="catalog-layout">
        <aside class="catalog-sidebar" aria-label="Фильтры каталога">
          <CatalogFilterForm
            v-model="draft"
            auto-apply
            :filters="draftFilters"
            :collections="collections"
            :pending="draftFiltersPending"
            :error="draftFiltersError"
            :hide-brand="!!query.brand"
            :scoped="scoped"
            @retry="refreshDraftFilters()"
            :busy="status === 'pending'"
            @apply="commit(undefined, $event)"
            @reset="scoped ? clearFilters() : clear()"
          />
        </aside>
        <div
          class="catalog-results"
          :aria-busy="status === 'pending' || loadingMore"
        >
          <div class="catalog-toolbar">
            <div class="catalog-toolbar__heading">
              <h2>{{ listingTitle }}</h2>
              <p role="status">
                Товаров: {{ catalog?.meta.total ?? '—'
                }}<span v-if="status === 'pending'"> · Обновляем…</span>
              </p>
            </div>
            <UiButton
              class="catalog-mobile-toggle"
              variant="outline"
              size="compact"
              @click="openFilters()"
              >Фильтры</UiButton
            >
            <UiSelect
              v-model="sorting"
              label="Сортировка"
              size="compact"
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
              size="small"
              variant="outline"
              :aria-label="'Убрать фильтр: ' + chip.label"
              @click="remove(chip.key, chip.id)"
              >{{ chip.label }} ×</UiButton
            >
            <UiButton
              size="small"
              variant="outline"
              @click="scoped ? clearFilters() : clear()"
              >{{ scoped ? 'Сбросить фильтры' : 'Сбросить всё' }}</UiButton
            >
            <NuxtLink v-if="scoped" :to="catalogLink" class="catalog-reset-link"
              >Весь каталог</NuxtLink
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
            v-else-if="status === 'pending' && !catalog"
            title="Товары"
            message="Загружаем каталог…"
            loading
          />
          <template v-else>
            <ProductCardList
              v-if="catalog?.data.length"
              :products="products"
              class="products"
            />
            <template v-else>
              <PageState
                title="По вашему запросу ничего не найдено"
                message="Измените дополнительные фильтры или вернитесь ко всему каталогу."
              />
              <UiButton variant="outline" @click="clearFilters()"
                >Сбросить фильтры</UiButton
              >
            </template>
            <div v-if="products.length" class="catalog-load-more">
              <p ref="moreCounter" role="status" tabindex="-1">
                Показано {{ products.length }} из {{ listingTotal }}
              </p>
              <progress
                :value="products.length"
                :max="listingTotal"
                aria-label="Показано товаров"
              />
              <p v-if="moreError" role="alert">{{ moreError }}</p>
              <div v-if="hasMore" ref="moreControl" @click.capture="loadNext">
                <UiButton
                  :to="router.resolve(pageLink(nextPage)).href"
                  variant="outline"
                  :aria-disabled="loadingMore || status === 'pending'"
                  >{{
                    loadingMore ? 'Загружаем…' : `Показать ещё ${nextCount}`
                  }}</UiButton
                >
              </div>
              <NuxtLink
                v-if="catalog && catalog.meta.current_page > 1"
                :to="pageLink(catalog.meta.current_page - 1)"
                >← Предыдущая</NuxtLink
              >
            </div>
          </template>
        </div>
      </div>
    </div>
    <UiDialog
      v-if="drawer"
      v-slot="{ close }"
      title="Фильтры каталога"
      size="drawer"
      @close="closeFilters()"
    >
      <div class="catalog-drawer-body">
        <CatalogCategories
          v-if="draftCategories.length || draft.brand"
          :categories="draftCategories"
          :query="{ ...query, category: draft.category, brand: draft.brand }"
          :path="route.path"
          :brand="
            draft.brand ? draftNavigation?.brand?.name || draft.brand : ''
          "
          :total="draftNavigation?.total"
          vertical
          @select="selectCategory"
        />
        <CatalogFilterForm
          v-model="draft"
          :filters="draftFilters"
          :collections="collections"
          :pending="draftFiltersPending"
          :error="draftFiltersError"
          :hide-brand="!!query.brand"
          :scoped="!!(draft.category || draft.brand)"
          @retry="refreshDraftFilters()"
          :busy="status === 'pending'"
          @apply="commit(close)"
          @reset="resetDraft()"
        />
      </div>
    </UiDialog>
  </section>
</template>

<style scoped>
.standalone-catalog {
  scroll-margin-top: var(--catalog-header-height);
  padding-top: 55px;
}
.catalog-reset-link {
  display: inline-flex;
  min-height: 28px;
  align-items: center;
  font-size: 13px;
}
.catalog-category-navigation {
  justify-content: center;
  display: flex;
  align-items: baseline;
  gap: 12px 32px;
  position: sticky;
  z-index: 40;
  top: var(--catalog-header-height);
  max-height: calc(100dvh - var(--catalog-header-height) - 44px);
  overflow-y: auto;
  margin-top: -16px;
  margin-bottom: 24px;
  border-bottom: 1px solid transparent;
  padding-block: 16px 15px;
  background: var(--color-bg);
}
.catalog-category-navigation .catalog-categories {
  width: 100%;
  min-width: 0;
}
.catalog-category-navigation--pinned {
  border-bottom-color: var(--color-line);
  border-image-source: linear-gradient(var(--color-line), var(--color-line));
  border-image-slice: 1;
  border-image-width: 0 0 1px;
  border-image-outset: 0 100vw 0;
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
.catalog-sidebar {
  padding: 20px;
  background: var(--color-beige);
  --field-background: var(--color-bg-soft);
  --field-border-color: var(--color-line-strong);
  --color-muted: var(--color-ink-soft);
  --color-line: var(--color-line-strong);
}
.catalog-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 20px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--color-line);
  padding-bottom: 16px;
}
.catalog-toolbar p {
  margin: 0;
  padding-bottom: 8px;
  font-size: 13px;
  color: var(--color-muted);
}
.catalog-toolbar__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 20px;
  min-width: 0;
}
.catalog-toolbar__heading h2 {
  margin: 0;
  font-size: clamp(22px, 2.4vw, 30px);
  font-weight: 500;
  line-height: 1.25;
  overflow-wrap: anywhere;
}
.catalog-toolbar .ui-select {
  width: min(230px, 100%);
  --field-background: var(--color-bg-soft);
}
.catalog-toolbar .catalog-mobile-toggle {
  display: none;
}
.catalog-applied {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
}
.catalog-applied :deep(.ui-button) {
  max-width: 100%;
  white-space: normal;
  overflow-wrap: anywhere;
}
.catalog-load-more {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  margin-top: 48px;
  font-size: 12px;
  color: var(--color-muted);
}
.catalog-load-more p {
  margin: 0;
}
.catalog-load-more progress {
  appearance: none;
  width: 200px;
  max-width: 100%;
  height: 2px;
  border: 0;
  background: var(--color-line);
  color: var(--color-ink);
}
.catalog-load-more progress::-webkit-progress-bar {
  background: var(--color-line);
}
.catalog-load-more progress::-webkit-progress-value {
  background: var(--color-ink);
}
.catalog-load-more progress::-moz-progress-bar {
  background: var(--color-ink);
}
.catalog-load-more :deep([aria-disabled='true']) {
  opacity: 0.55;
  cursor: wait;
}
.catalog-drawer-body {
  overflow-y: auto;
  padding: 24px var(--ui-drawer-gutter);
}
.catalog-drawer-body .catalog-categories + .catalog-filter-form {
  margin-top: 20px;
}
@media (pointer: coarse) {
  .catalog-reset-link {
    min-height: 44px;
  }
}
@media (max-width: 1023px) {
  .catalog-category-navigation {
    display: none;
  }
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
