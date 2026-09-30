import { imageUrl, mapContentBlock } from './homePage'
import type { HomePageDto } from './homePage'
import type {
  CatalogResult,
  ContentBlockDto,
  ContentPage,
  PublicStore,
  SellerContacts,
} from '~/types/contentPage'

export async function fetchContentPage(
  slug: string,
  requestBase: string,
  publicBase: string,
): Promise<ContentPage> {
  const { data } = await $fetch<{
    data: {
      title: string
      slug: string
      body: string
      blocks: ContentBlockDto[]
      seo: HomePageDto['seo']
    }
  }>(apiUrl(`pages/${encodeURIComponent(slug)}`, requestBase))
  return {
    title: data.title,
    slug: data.slug,
    body: data.body,
    blocks: data.blocks.map((block) => mapContentBlock(block, publicBase)),
    seo: {
      title: data.seo.title || data.title,
      description: data.seo.description || '',
      ogTitle: data.seo.og_title || data.seo.title || data.title,
      ogDescription: data.seo.og_description || data.seo.description || '',
      ogImage: imageUrl(data.seo.og_image_url, publicBase),
    },
  }
}

function apiUrl(path: string, base: string): string {
  return new URL(path, base.endsWith('/') ? base : `${base}/`).toString()
}

export async function fetchStores(base: string): Promise<PublicStore[]> {
  // The stores API is paginated. Contacts must include every published store.
  const stores: PublicStore[] = []
  let page = 1
  let lastPage = 1
  do {
    const response = await $fetch<{
      data: PublicStore[]
      meta: { last_page: number }
    }>(apiUrl('stores', base), { query: { page } })
    stores.push(...response.data)
    lastPage = response.meta.last_page
    page++
  } while (page <= lastPage)
  return stores
}

export async function fetchSellerContacts(
  base: string,
): Promise<SellerContacts> {
  const response = await $fetch<{ data: SellerContacts }>(
    apiUrl('site-settings', base),
  )
  return response.data
}

export async function fetchCatalog(
  base: string,
  publicBase: string,
  page: number,
): Promise<CatalogResult> {
  const response = await $fetch<CatalogResult>(apiUrl('catalog', base), {
    query: { page },
  })
  return {
    ...response,
    data: response.data.map((product) => ({
      ...product,
      image_url: imageUrl(product.image_url, publicBase),
    })),
    categories: response.categories.map((category) => ({
      ...category,
      image_url: imageUrl(category.image_url, publicBase),
    })),
  }
}
