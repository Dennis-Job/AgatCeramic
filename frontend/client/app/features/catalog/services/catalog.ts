import { imageUrl } from '~/services/homePage'
import type { CatalogQuery, StandaloneCatalog } from '../types/catalog'

export async function fetchStandaloneCatalog(
  requestBase: string,
  publicBase: string,
  query: CatalogQuery,
  signal?: AbortSignal,
): Promise<StandaloneCatalog> {
  const response = await $fetch<StandaloneCatalog>(
    new URL('catalog', requestBase.replace(/\/?$/, '/')).toString(),
    {
      query: { ...query, include_filters: 1, category_descendants: 1 },
      signal,
      retry: 0,
    },
  )
  return {
    ...response,
    categories: response.categories,
    filters: response.filters || {
      brands: [],
      collections: [],
      attributes: [],
    },
    data: response.data.map((product) => ({
      ...product,
      image_url: imageUrl(product.image_url, publicBase) || null,
    })),
  }
}
