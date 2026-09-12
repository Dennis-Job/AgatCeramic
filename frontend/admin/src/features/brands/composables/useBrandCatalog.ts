import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { deleteBrand, getBrands, saveBrand } from '../services/brands'
import type { Brand, BrandPayload } from '../types/brand.types'

export function useBrandCatalog() {
  const list = usePaginatedCollection<Brand>('Не удалось загрузить бренды.')
  async function load(page = list.pagination.value?.current_page ?? 1): Promise<void> { await list.load(page, requestedPage => getBrands({ page: requestedPage })) }
  async function loadAfterDeletion(): Promise<void> { await list.reloadAfterDeletion(page => getBrands({ page })) }
  return { brands: list.items, pagination: list.pagination, error: list.error, loading: list.loading, load, loadAfterDeletion, saveBrand: (id: number | null, payload: BrandPayload) => saveBrand(id, payload), removeBrand: deleteBrand }
}
