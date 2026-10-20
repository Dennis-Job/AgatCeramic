/** Public CMS slugs match the API contract. Preview is a reserved service route. */
export function contentPagePath(slug: string): string | null {
  if (
    slug.length > 255 ||
    !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) ||
    slug === 'preview'
  )
    return null
  return slug === 'home' ? '/' : `/${slug}`
}

export function siteOrigin(value: string): string {
  const url = new URL(value)
  if (!['https:', 'http:'].includes(url.protocol))
    throw new Error('Invalid site URL')
  return url.origin
}
