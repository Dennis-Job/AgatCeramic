export function safeLink(url: string | null | undefined): string | null {
  if (!url || /[\\\s\u0000-\u001f\u007f]/.test(url)) return null
  if (url.startsWith('/') && !url.startsWith('//')) return url
  try {
    const parsed = new URL(url)
    return parsed.protocol === 'https:' && !parsed.username && !parsed.password
      ? url
      : null
  } catch {
    return null
  }
}

export function imageUrl(
  url: string | null | undefined,
  apiBase: string,
): string {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  if (!url.startsWith('/') || url.startsWith('//')) return ''
  if (url.startsWith('/images/home/') || url.startsWith('/images/page-intro/'))
    return url
  try {
    return new URL(url, apiBase).toString()
  } catch {
    return ''
  }
}
