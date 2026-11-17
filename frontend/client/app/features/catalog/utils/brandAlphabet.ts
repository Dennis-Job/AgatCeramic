import type { CatalogBrand } from '../types/catalog'

export interface BrandAlphabetGroup {
  key: string
  label: string
  brands: CatalogBrand[]
}

export function groupBrands(brands: CatalogBrand[]): BrandAlphabetGroup[] {
  const groups = new Map<string, CatalogBrand[]>()
  for (const brand of brands) {
    // Ignore leading whitespace/quotation marks, but group by the visible name.
    const name = brand.name.trim().replace(/^[\s"'«»„“”‘’]+/u, '')
    const first =
      Array.from(
        name.normalize('NFD').replace(/\p{M}/gu, ''),
      )[0]?.toUpperCase() || ''
    const key = /^\p{N}$/u.test(first)
      ? '0–9'
      : /^\p{Script=Cyrillic}$/u.test(first)
        ? 'РУС'
        : /^[A-Z]$/.test(first)
          ? first
          : ''
    if (!key) continue
    const entries = groups.get(key) || []
    entries.push(brand)
    groups.set(key, entries)
  }
  const collator = new Intl.Collator('ru', {
    sensitivity: 'base',
    numeric: true,
    ignorePunctuation: true,
  })
  return ['0–9', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'РУС'].flatMap((key) => {
    const entries = groups.get(key)
    return entries
      ? [
          {
            key,
            label: key,
            brands: [...entries].sort(
              (a, b) =>
                collator.compare(a.name.trim(), b.name.trim()) || a.id - b.id,
            ),
          },
        ]
      : []
  })
}
