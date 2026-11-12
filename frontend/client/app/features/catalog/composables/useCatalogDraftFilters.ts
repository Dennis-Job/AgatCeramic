import type { ComputedRef, Ref } from 'vue'
import { fetchStandaloneCatalog } from '../services/catalog'
import type {
  CatalogDraft,
  CatalogFilters,
  CatalogQuery,
  StandaloneCatalog,
} from '../types/catalog'

/** Loads choices for the editable scope without replacing the applied listing. */
export function useCatalogDraftFilters(
  draft: Ref<CatalogDraft>,
  query: ComputedRef<CatalogQuery>,
  catalog: Ref<StandaloneCatalog | null | undefined>,
  syncing: () => boolean,
) {
  const { requestBase, publicBase } = usePublicApiConfig()
  const pending = ref(false)
  const error = ref('')
  const choices = shallowRef<{ scope: string; filters: CatalogFilters } | null>(
    null,
  )
  let controller: AbortController | undefined
  const scope = () => JSON.stringify([draft.value.category, draft.value.brand])
  const appliedScope = () =>
    JSON.stringify([query.value.category || '', query.value.brand || ''])
  const filters = computed<CatalogFilters>(() => {
    if (choices.value?.scope === scope()) return choices.value.filters
    if (scope() === appliedScope() && catalog.value)
      return catalog.value.filters
    return {
      brands: catalog.value?.filters.brands || [],
      collections: [],
      attributes: [],
    }
  })
  const collections = computed(() =>
    draft.value.brand ? filters.value.collections : [],
  )

  function clear() {
    controller?.abort()
    controller = undefined
    pending.value = false
    error.value = ''
    choices.value = null
  }

  async function refresh(force = false) {
    clear()
    if (!force && scope() === appliedScope() && catalog.value) return
    const requestScope = scope()
    const lookup = new AbortController()
    controller = lookup
    pending.value = true
    try {
      const response = await fetchStandaloneCatalog(
        requestBase,
        publicBase,
        {
          ...(draft.value.category ? { category: draft.value.category } : {}),
          ...(draft.value.brand ? { brand: draft.value.brand } : {}),
        },
        lookup.signal,
      )
      if (!lookup.signal.aborted && requestScope === scope())
        choices.value = { scope: requestScope, filters: response.filters }
    } catch {
      if (!lookup.signal.aborted && requestScope === scope())
        error.value = 'Не удалось загрузить фильтры. Повторите попытку.'
    } finally {
      if (controller === lookup) pending.value = false
    }
  }

  watch(
    () => [draft.value.category, draft.value.brand] as const,
    ([category, brand], previous) => {
      if (syncing()) {
        clear()
        return
      }
      if (category === previous[0] && brand === previous[1]) return
      if (category !== previous[0]) draft.value.attributes = {}
      draft.value.collection = ''
      void refresh()
    },
    { flush: 'sync' },
  )
  watch(
    () => catalog.value?.filters,
    () => {
      if (scope() === appliedScope()) clear()
    },
  )
  onScopeDispose(clear)
  return { filters, collections, pending, error, clear, refresh }
}
