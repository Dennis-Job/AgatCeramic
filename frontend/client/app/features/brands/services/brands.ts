import { imageUrl } from '~/utils/siteUrls'
import type { PublicBrand } from '../types/brand'

interface PublicBrandDto {
  id: number
  name: string
  slug: string
  description: string | null
  logo_url: string | null
  logo_alt: string
}

export function brandPath(slug: string): string {
  return '/brand/' + encodeURIComponent(slug)
}

export async function fetchPublicBrand(
  slug: string,
  requestBase: string,
  publicBase: string,
  signal?: AbortSignal,
): Promise<PublicBrand> {
  const response = await $fetch<{ data: PublicBrandDto }>(
    new URL(
      'brands/' + encodeURIComponent(slug),
      requestBase.replace(/\/?$/, '/'),
    ).toString(),
    { retry: 0, timeout: 10_000, signal },
  )
  return {
    id: response.data.id,
    name: response.data.name,
    slug: response.data.slug,
    description: response.data.description,
    logoUrl: imageUrl(response.data.logo_url, publicBase) || null,
    logoAlt: response.data.logo_alt || response.data.name,
  }
}
