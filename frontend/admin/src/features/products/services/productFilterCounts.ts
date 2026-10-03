import { apiFetch } from '../../../services/auth'
import type {
  ProductFilterCounts,
  ProductFilters,
} from '../types/product.types'

type ProductFilterCountResponse = { data: ProductFilterCounts }

export async function getProductFilterCounts(
  filters: Pick<
    ProductFilters,
    | 'search'
    | 'category_id'
    | 'brand_id'
    | 'is_active'
    | 'is_on_sale'
    | 'has_stock'
    | 'price_from'
    | 'price_to'
  >,
): Promise<ProductFilterCounts> {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '')
      query.set(
        key,
        typeof value === 'boolean' ? (value ? '1' : '0') : String(value),
      )
  })

  const path = '/admin/products/filter-counts'
  const response = await apiFetch(query.size ? path + '?' + query : path)
  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as {
      error?: { message?: string; details?: Record<string, string[]> }
    }
    throw new Error(
      Object.values(body.error?.details ?? {}).flat()[0] ??
        body.error?.message ??
        'Не удалось обновить счётчики товаров.',
    )
  }
  return ((await response.json()) as ProductFilterCountResponse).data
}
