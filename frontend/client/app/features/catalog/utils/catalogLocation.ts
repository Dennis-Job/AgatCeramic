import type { CatalogQuery } from '../types/catalog'

/** Switching categories clears constraints specific to the previous category. */
export function catalogCategoryLocation(
  path: string,
  current: CatalogQuery,
  category?: string,
) {
  const query = { ...current }
  delete query.page
  delete query.attributes
  delete query.collection
  if (category) query.category = category
  else delete query.category
  return catalogLocation(path, query)
}

/** Dedicated pages keep their fixed brand or collection out of the URL query. */
export function catalogLocation(
  path: string,
  query: CatalogQuery,
  hash = '#catalog-products',
) {
  if (path.startsWith('/collection/')) {
    const filters = { ...query }
    delete filters.brand
    delete filters.collection
    delete filters.collection_slug
    return { path, query: filters, hash }
  }
  if (!path.startsWith('/brand/')) return { path, query, hash }
  const { brand, ...filters } = query
  return {
    path: brand ? path : '/catalog',
    query: filters,
    hash,
  }
}
