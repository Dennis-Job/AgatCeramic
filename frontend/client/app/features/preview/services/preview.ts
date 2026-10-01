import {
  imageUrl,
  mapContentBlock,
  mapSiteAppearance,
} from '~/services/homePage'
import type { DraftPreview, DraftPreviewDto } from '../types/preview'

export async function fetchDraftPreview(
  slug: string,
  base: string,
  signal: AbortSignal,
): Promise<DraftPreview> {
  const { data } = await $fetch<{ data: DraftPreviewDto }>(
    new URL(
      `admin/content-preview/${encodeURIComponent(slug)}`,
      base.endsWith('/') ? base : `${base}/`,
    ).toString(),
    {
      credentials: 'include',
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      // Sanctum identifies the SPA by Origin/Referer, including same-origin GETs.
      // Only the origin is sent to our API; page path is never disclosed.
      referrerPolicy: 'origin',
      retry: 0,
      timeout: 10_000,
      signal,
    },
  )
  return {
    page: {
      ...data.page,
      blocks: data.page.blocks.map((block) => mapContentBlock(block, base)),
      seo: {
        title: data.page.seo.title || data.page.title,
        description: data.page.seo.description || '',
        ogTitle: data.page.seo.og_title || data.page.title,
        ogDescription: data.page.seo.og_description || '',
        ogImage: imageUrl(data.page.seo.og_image_url, base),
      },
    },
    appearance: mapSiteAppearance(data.appearance, base),
  }
}
