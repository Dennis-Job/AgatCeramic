/** Legacy /images assets belong to the storefront, not the Admin origin. */
export function contentImageUrl(url: string | null | undefined): string | null {
  if (!url) return null
  if (!url.startsWith('/images/')) return url
  return new URL(
    url,
    import.meta.env.VITE_CLIENT_URL || 'http://localhost:3000',
  ).href
}
