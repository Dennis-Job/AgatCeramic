import { fetchStandaloneCatalog } from '../services/catalog'
import { useCatalogDraftFilters } from './useCatalogDraftFilters'
import type {
  AttributeFilter,
  CatalogDraft,
  CatalogQuery,
} from '../types/catalog'

const keys = [
  'category',
  'brand',
  'collection',
  'price_from',
  'price_to',
  'in_stock',
  'sort',
  'attributes',
  'page',
] as const

function draftFrom(query: CatalogQuery): CatalogDraft {
  let attributes: Record<string, AttributeFilter> = {}
  try {
    const parsed: unknown = JSON.parse(query.attributes || '{}')
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      attributes = Object.fromEntries(
        Object.entries(parsed).flatMap(([id, raw]) => {
          if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return []
          const filter = raw as Record<string, unknown>
          return [
            [
              id,
              {
                ...(Array.isArray(filter.values) &&
                filter.values.every((value) => typeof value === 'string')
                  ? { values: filter.values }
                  : {}),
                ...(typeof filter.min === 'string' ? { min: filter.min } : {}),
                ...(typeof filter.max === 'string' ? { max: filter.max } : {}),
                ...(typeof filter.text === 'string'
                  ? { text: filter.text }
                  : {}),
              },
            ],
          ]
        }),
      )
    }
  } catch {
    /* Invalid direct links remain in the request for server validation. */
  }
  return {
    category: query.category || '',
    brand: query.brand || '',
    collection: query.collection || '',
    price_from: query.price_from || '',
    price_to: query.price_to || '',
    in_stock: query.in_stock === '1',
    attributes,
  }
}

export function useCatalog() {
  const route = useRoute()
  const { requestBase, publicBase } = usePublicApiConfig()
  const query = computed<CatalogQuery>(() =>
    Object.fromEntries(
      keys.flatMap((key) => {
        const value = route.query[key]
        return typeof value === 'string' && value !== '' ? [[key, value]] : []
      }),
    ),
  )
  const draft = ref(draftFrom(query.value))
  const invalidQuery = computed(() =>
    keys.some(
      (key) =>
        route.query[key] !== undefined && typeof route.query[key] !== 'string',
    ),
  )
  const result = useAsyncData(
    () =>
      'standalone-catalog:' + JSON.stringify([query.value, invalidQuery.value]),
    (_app, { signal }) => {
      if (invalidQuery.value)
        throw createError({ statusCode: 422, message: 'Invalid catalog link' })
      return fetchStandaloneCatalog(
        requestBase,
        publicBase,
        query.value,
        signal,
      )
    },
  )
  let syncingDraft = false
  const draftChoices = useCatalogDraftFilters(
    draft,
    query,
    result.data,
    () => syncingDraft,
  )
  watch(query, (value) => {
    draftChoices.clear()
    syncingDraft = true
    draft.value = draftFrom(value)
    syncingDraft = false
  })
  function navigate(value: CatalogQuery, hash = '#catalog-products') {
    return navigateTo({ path: route.path, query: value, hash })
  }
  function apply() {
    const value: CatalogQuery = {}
    if (query.value.sort) value.sort = query.value.sort
    if (draft.value.category) value.category = draft.value.category
    for (const key of [
      'brand',
      'collection',
      'price_from',
      'price_to',
    ] as const)
      if (draft.value[key].trim()) value[key] = draft.value[key].trim()
    if (draft.value.in_stock) value.in_stock = '1'
    const attributes = Object.fromEntries(
      Object.entries(draft.value.attributes).flatMap(([id, filter]) => {
        if (!filter || typeof filter !== 'object') return []
        const clean = {
          ...(Array.isArray(filter.values) && filter.values.length
            ? { values: filter.values }
            : {}),
          ...(filter.min?.trim() ? { min: filter.min.trim() } : {}),
          ...(filter.max?.trim() ? { max: filter.max.trim() } : {}),
          ...(filter.text?.trim() ? { text: filter.text.trim() } : {}),
        }
        return Object.keys(clean).length ? [[id, clean]] : []
      }),
    )
    if (Object.keys(attributes).length)
      value.attributes = JSON.stringify(attributes)
    return navigate(value)
  }
  function remove(key: string, attributeId?: string) {
    const value = { ...query.value }
    delete value.page
    if (key === 'attributes' && attributeId) {
      const attributes = { ...draftFrom(value).attributes }
      delete attributes[attributeId]
      if (Object.keys(attributes).length)
        value.attributes = JSON.stringify(attributes)
      else delete value.attributes
    } else {
      delete value[key]
      if (key === 'brand') delete value.collection
      if (key === 'category') delete value.attributes
    }
    return navigate(value)
  }
  function sort(value: string) {
    const next = { ...query.value }
    delete next.page
    if (value === 'name_asc') delete next.sort
    else next.sort = value
    return navigate(next)
  }
  function clearDraft() {
    draftChoices.clear()
    syncingDraft = true
    draft.value = draftFrom({})
    syncingDraft = false
  }
  function resetDraft() {
    clearDraft()
    void draftChoices.refresh()
  }
  function reset() {
    const target: CatalogQuery = query.value.sort
      ? { sort: query.value.sort }
      : {}
    clearDraft()
    return navigate(target)
  }
  return {
    ...result,
    ready: result,
    query,
    draft,
    collections: draftChoices.collections,
    draftFilters: draftChoices.filters,
    draftFiltersPending: draftChoices.pending,
    draftFiltersError: draftChoices.error,
    refreshDraftFilters: () => draftChoices.refresh(true),
    selectCategory: (category: string) => {
      draft.value.category = category
    },
    resetDraft,
    apply,
    remove,
    sort,
    reset,
    pageLink: (page: number) => ({
      path: route.path,
      query: {
        ...query.value,
        ...(page > 1 ? { page: String(page) } : { page: undefined }),
      },
      hash: '#catalog-products',
    }),
  }
}
