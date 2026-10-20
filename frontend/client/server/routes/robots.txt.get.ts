import { siteOrigin } from '../../shared/utils/pageUrls'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig(event)
  const origin = siteOrigin(
    config.public.siteUrl || getRequestURL(event).origin,
  )
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  return `User-Agent: *\nDisallow: /preview/\nSitemap: ${origin}/sitemap.xml\n`
})
