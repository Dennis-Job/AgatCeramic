import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import {
  deleteBrand,
  getBrands,
  saveBrand,
  updateBrandCatalogMenu,
} from '../services/brands'
import type { Brand, BrandPayload } from '../types/brand.types'

export function useBrandCatalog() {
  const list = usePaginatedCollection<Brand>('Не удалось загрузить бренды.')
  const search = ref('')
  const searchPending = ref(false)
  const menuPendingIds = ref(new Set<number>())
  const menuError = ref('')
  let menuRevision = 0
  const menuUpdates = new Map<number, { revision: number; value: boolean }>()
  let searchTimer: ReturnType<typeof setTimeout> | undefined
  let loadedSearch = ''

  function preserveMenuUpdates(requestedRevision: number): void {
    // A list fetch that started before a completed PATCH may contain the old flag.
    list.items.value = list.items.value.map((item) => {
      const update = menuUpdates.get(item.id)
      return update && update.revision > requestedRevision
        ? { ...item, show_in_catalog_menu: update.value }
        : item
    })
  }

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
    const requestedRevision = menuRevision
    cancelScheduledSearch()
    const response = await list.load(page, (requestedPage) =>
      getBrands({ page: requestedPage, search: query }),
    )
    if (response) {
      loadedSearch = query
      preserveMenuUpdates(requestedRevision)
    }
  }
  async function loadAfterDeletion(): Promise<void> {
    if (searchPending.value) return load(1)
    const query = search.value.trim()
    const requestedRevision = menuRevision
    const response = await list.reloadAfterDeletion((page) =>
      getBrands({ page, search: query }),
    )
    if (response) preserveMenuUpdates(requestedRevision)
  }
  async function setCatalogMenu(brand: Brand, visible: boolean): Promise<void> {
    if (menuPendingIds.value.has(brand.id)) return
    menuPendingIds.value.add(brand.id)
    menuError.value = ''
    try {
      const saved = await updateBrandCatalogMenu(brand.id, visible)
      menuUpdates.set(brand.id, {
        revision: ++menuRevision,
        value: saved.show_in_catalog_menu,
      })
      list.items.value = list.items.value.map((item) =>
        item.id === brand.id
          ? { ...item, show_in_catalog_menu: saved.show_in_catalog_menu }
          : item,
      )
    } catch (reason) {
      menuError.value = `Бренд «${brand.name}»: ${reason instanceof Error ? reason.message : 'Не удалось изменить отображение в алфавитном меню.'}`
    } finally {
      menuPendingIds.value.delete(brand.id)
    }
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
    menuPendingIds,
    menuError,
    setCatalogMenu,
    load,
    loadAfterDeletion,
    saveBrand: (id: number | null, payload: BrandPayload) =>
      saveBrand(id, payload),
    removeBrand: deleteBrand,
  }
}
