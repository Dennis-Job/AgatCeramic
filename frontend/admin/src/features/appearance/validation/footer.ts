import type { SiteAppearance } from '../types/appearance.types'
import { linkError } from './navigation'

export function footerError(footer: SiteAppearance['footer']): string {
  for (const [title, items, max] of [
    ['Навигация', footer.navigation_links ?? [], 256],
    ['Исследовать', footer.explore_links, 12],
    ['Мы в соцсетях', footer.social_links ?? [], 12],
  ] as const) {
    if (items.length > max) return `«${title}»: допустимо до ${max} ссылок.`
    for (const [index, item] of items.entries()) {
      const error = linkError(item, `«${title}», ссылка ${index + 1}`)
      if (error) return error
    }
  }
  if (footer.bottom_right_url) {
    return linkError(
      { label: footer.bottom_right, to: footer.bottom_right_url },
      'Нижняя строка справа',
    )
  }
  return ''
}
