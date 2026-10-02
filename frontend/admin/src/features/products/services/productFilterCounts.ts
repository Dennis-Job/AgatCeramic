import { getProducts } from './products'
import type {
  ProductFilterCounts,
  ProductFilters,
} from '../types/product.types'

export async function getProductFilterCounts(
  filters: Pick<ProductFilters, 'search' | 'category_id' | 'brand_id'>,
): Promise<ProductFilterCounts> {
  const results = await Promise.allSettled([
    getProducts({ ...filters, is_active: true, perPage: 1 }),
    getProducts({ ...filters, is_active: false, perPage: 1 }),
    getProducts({ ...filters, is_on_sale: true, perPage: 1 }),
    getProducts({ ...filters, is_on_sale: false, perPage: 1 }),
  ])
  const total = (index: number): number | null => {
    const result = results[index]
    return result?.status === 'fulfilled' ? result.value.meta.total : null
  }
  return {
    active: total(0),
    hidden: total(1),
    sale: total(2),
    regular: total(3),
  }
}
