import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { deleteBrand, getBrands, saveBrand } from '../services/brands'
import type { Brand, BrandPayload } from '../types/brand.types'

export function useBrandCatalog() {
  const list = usePaginatedCollection<Brand>('Не удалось загрузить бренды.')
  const search = ref('')
  const searchPending = ref(false)
  let searchTimer: ReturnType<typeof setTimeout> | undefined
  let loadedSearch = ''

  function cancelScheduledSearch(): void {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = undefined
    searchPending.value = false
  }

  async function load(
    page = searchPending.value || search.value.trim() !== loadedSearch
      ? 1
      : (list.pagination.value?.current_page ?? 1),
  ): Promise<void> {
    const query = search.value.trim()
    cancelScheduledSearch()
    const response = await list.load(page, (requestedPage) =>
      getBrands({ page: requestedPage, search: query }),
    )
    if (response) loadedSearch = query
  }
  async function loadAfterDeletion(): Promise<void> {
    if (searchPending.value) return load(1)
    const query = search.value.trim()
    await list.reloadAfterDeletion((page) => getBrands({ page, search: query }))
  }
  watch(
    () => search.value.trim(),
    () => {
      cancelScheduledSearch()
      searchPending.value = true
      searchTimer = setTimeout(() => void load(1), 350)
    },
    { flush: 'sync' },
  )
  onBeforeUnmount(cancelScheduledSearch)

  return {
    search,
    brands: list.items,
    pagination: list.pagination,
    error: list.error,
    loading: computed(() => list.loading.value || searchPending.value),
    load,
    loadAfterDeletion,
    saveBrand: (id: number | null, payload: BrandPayload) =>
      saveBrand(id, payload),
    removeBrand: deleteBrand,
  }
}
