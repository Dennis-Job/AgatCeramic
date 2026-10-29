import { contentPagePath, siteOrigin } from '../../shared/utils/pageUrls'

interface PageSummary {
  slug: string
  is_indexable?: boolean
  published_at?: string | null
}

interface PageCollection {
  data: PageSummary[]
  meta: { current_page: number; last_page: number }
}

function escapeXml(value: string): string {
  return value.replace(
    /[<>&"']/g,
    (character) =>
      ({
        '<': '&lt;',
        '>': '&gt;',
        '&': '&amp;',
        '"': '&quot;',
        "'": '&apos;',
      })[character]!,
  )
}

function productPagePath(slug: string): string | null {
  if (slug.length > 255 || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) return null
  return `/products/${slug}`
}

export default defineEventHandler(async (event) => {
  // Publication and indexing can change at any time. Never serve a stale/partial list.
  setHeader(event, 'Cache-Control', 'no-store')
  try {
    const config = useRuntimeConfig(event)
    const origin = siteOrigin(
      config.public.siteUrl || getRequestURL(event).origin,
    )
    const base = config.apiBaseInternal || config.public.apiBase
    const entries = new Map<string, string>()
    for (const resource of ['pages', 'catalog'] as const) {
      const endpoint = new URL(
        resource,
        base.endsWith('/') ? base : `${base}/`,
      ).toString()
      let lastPage = 1
      for (let page = 1; page <= lastPage; page++) {
        const response = await $fetch<PageCollection>(endpoint, {
          query: { page },
          retry: 0,
          timeout: 10_000,
        })
        if (
          !Array.isArray(response?.data) ||
          response.meta?.current_page !== page ||
          !Number.isInteger(response.meta?.last_page) ||
          response.meta.last_page < page ||
          response.meta.last_page > 10_000 ||
          (page > 1 && response.meta.last_page !== lastPage)
        )
          throw new Error('Invalid sitemap collection')
        lastPage = response.meta.last_page
        for (const entry of response.data) {
          if (!entry || typeof entry.slug !== 'string')
            throw new Error('Invalid sitemap summary')
          const path =
            resource === 'pages'
              ? contentPagePath(entry.slug)
              : productPagePath(entry.slug)
          if (!path || (resource === 'pages' && entry.is_indexable === false))
            continue
          const url = new URL(path, origin).toString()
          const lastmod =
            resource === 'pages' &&
            typeof entry.published_at === 'string' &&
            Number.isFinite(Date.parse(entry.published_at))
              ? `<lastmod>${escapeXml(entry.published_at)}</lastmod>`
              : ''
          entries.set(url, `<url><loc>${escapeXml(url)}</loc>${lastmod}</url>`)
        }
      }
    }
    setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
    return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${[...entries.values()].join('')}</urlset>`
  } catch {
    throw createError({
      statusCode: 503,
      statusMessage: 'Sitemap temporarily unavailable',
    })
  }
})
