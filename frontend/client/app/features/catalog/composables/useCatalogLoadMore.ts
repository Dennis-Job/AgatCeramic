import type { ComputedRef } from 'vue'
import { fetchStandaloneCatalog } from '../services/catalog'
import type { CatalogQuery, StandaloneCatalog } from '../types/catalog'

export function useCatalogLoadMore(
  catalog: ComputedRef<StandaloneCatalog | null | undefined>,
  query: ComputedRef<CatalogQuery>,
  pending: ComputedRef<boolean>,
) {
  const route = useRoute()
  const { requestBase, publicBase } = usePublicApiConfig()
  const extra = shallowRef<StandaloneCatalog['data']>([])
  const pagination = shallowRef<StandaloneCatalog['meta'] | null>(null)
  const loading = ref(false)
  const error = ref('')
  let controller: AbortController | undefined
  const key = computed(() => JSON.stringify([route.path, query.value]))
  function reset() {
    controller?.abort()
    controller = undefined
    extra.value = []
    pagination.value = null
    loading.value = false
    error.value = ''
  }
  watch([key, catalog], reset, { flush: 'sync' })
  onScopeDispose(reset)
  const products = computed(() => [
    ...(catalog.value?.data || []),
    ...extra.value,
  ])
  const meta = computed(() => pagination.value || catalog.value?.meta)
  const hasMore = computed(
    () => !!meta.value && meta.value.current_page < meta.value.last_page,
  )
  const total = computed(() => meta.value?.total ?? 0)
  const nextPage = computed(() => (meta.value?.current_page ?? 0) + 1)
  // Later-page direct links use the API's fixed page size of 24.
  const pageSize = computed(() =>
    catalog.value?.meta.current_page === 1
      ? catalog.value.data.length || 24
      : 24,
  )
  const nextCount = computed(() =>
    Math.min(
      pageSize.value,
      Math.max(
        0,
        total.value - (meta.value?.current_page ?? 0) * pageSize.value,
      ),
    ),
  )
  async function loadMore() {
    if (loading.value || pending.value || !hasMore.value) return
    const scope = key.value
    const request = new AbortController()
    controller = request
    loading.value = true
    error.value = ''
    try {
      const response = await fetchStandaloneCatalog(
        requestBase,
        publicBase,
        { ...query.value, page: String(nextPage.value) },
        request.signal,
      )
      if (request.signal.aborted || key.value !== scope) return
      const ids = new Set(products.value.map((product) => product.id))
      extra.value = [
        ...extra.value,
        ...response.data.filter((product) => {
          if (ids.has(product.id)) return false
          ids.add(product.id)
          return true
        }),
      ]
      pagination.value = response.meta
    } catch {
      if (!request.signal.aborted && key.value === scope)
        error.value = 'Не удалось загрузить товары. Попробуйте ещё раз.'
    } finally {
      if (controller === request) {
        loading.value = false
        controller = undefined
      }
    }
  }
  return {
    products,
    loading,
    error,
    hasMore,
    total,
    nextPage,
    nextCount,
    loadMore,
  }
}
