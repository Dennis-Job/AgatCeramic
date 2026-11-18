import { brandPath } from '~/features/brands/services/brands'
import type { CatalogQuery } from '../types/catalog'

/** The brand belongs to the public path; protected previews keep their own route. */
export function catalogLocation(
  path: string,
  query: CatalogQuery,
  hash = '#catalog-products',
) {
  if (path.startsWith('/preview/')) return { path, query, hash }
  const next = { ...query }
  delete next.brand
  return {
    path: query.brand ? brandPath(query.brand) : '/catalog',
    query: next,
    hash,
  }
}
