import type { SiteLink, SiteNavigationItem } from '~/types/homePage'

export function searchNavigation(
  items: SiteNavigationItem[],
  query: string,
): SiteLink[] {
  const needle = query.trim().toLocaleLowerCase('ru')
  const seen = new Set<string>()
  return items.flatMap((item) => {
    const parentMatches = item.label.toLocaleLowerCase('ru').includes(needle)
    const links = 'children' in item ? item.children : [item]
    return links.filter((link) => {
      if (
        (!parentMatches &&
          !link.label.toLocaleLowerCase('ru').includes(needle)) ||
        seen.has(link.to)
      )
        return false
      seen.add(link.to)
      return true
    })
  })
}
