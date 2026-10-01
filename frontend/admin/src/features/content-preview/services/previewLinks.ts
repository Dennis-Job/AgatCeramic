export function previewLinks(
  slug: string,
  clientUrl = import.meta.env.VITE_CLIENT_URL || 'http://localhost:3000',
) {
  const base = new URL(clientUrl)
  if (
    !['http:', 'https:'].includes(base.protocol) ||
    base.username ||
    base.password ||
    base.search ||
    base.hash
  )
    throw new Error(
      'Адрес клиентского сайта должен быть HTTP(S) URL без учётных данных, query и fragment.',
    )
  base.pathname = `${base.pathname.replace(/\/$/, '')}/`
  return {
    draft: new URL(`preview/${encodeURIComponent(slug)}`, base).href,
    published: new URL(slug === 'home' ? '' : encodeURIComponent(slug), base)
      .href,
  }
}
