import { collectionPath } from '#shared/utils/pageUrls'
import { imageUrl } from '~/services/homePage'
import type { ProductCollection, PublicCollection } from '../types/collection'

export function productCollectionLink(
  collection: ProductCollection,
  pageAvailable = true,
) {
  return pageAvailable && collection.slug
    ? collectionPath(collection.brand_slug, collection.slug)
    : {
        path: '/catalog',
        query: { brand: collection.brand_slug, collection: collection.name },
      }
}

export async function fetchPublicCollection(
  brand: string,
  slug: string,
  requestBase: string,
  publicBase: string,
  signal?: AbortSignal,
): Promise<PublicCollection> {
  const endpoint = new URL(
    `brands/${encodeURIComponent(brand)}/collections/${encodeURIComponent(slug)}`,
    requestBase.replace(/\/?$/, '/'),
  )
  const { data } = await $fetch<{ data: PublicCollection }>(
    endpoint.toString(),
    {
      retry: 0,
      timeout: 10_000,
      signal,
    },
  )
  return { ...data, image_url: imageUrl(data.image_url, publicBase) || null }
}
