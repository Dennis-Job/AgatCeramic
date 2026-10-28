import type { SiteLink, SiteNavigationItem } from '../types/appearance.types'

function linkError(link: SiteLink, position: string): string {
  if (!link.label.trim() || link.label.length > 255)
    return `${position}: укажите название до 255 символов.`
  if (
    !link.to ||
    link.to.length > 2048 ||
    Array.from(link.to).some((character) => {
      const code = character.charCodeAt(0)
      return code <= 32 || code === 127 || character === '\\'
    })
  )
    return `${position}: укажите внутренний путь или HTTPS-адрес до 2048 символов.`
  if (link.to.startsWith('/') && !link.to.startsWith('//')) return ''
  try {
    const url = new URL(link.to)
    if (url.protocol === 'https:' && !url.username && !url.password) return ''
  } catch {
    /* Report the same actionable validation message below. */
  }
  return `${position}: укажите внутренний путь или безопасный HTTPS-адрес.`
}

export function navigationError(items: SiteNavigationItem[]): string {
  if (items.length < 1 || items.length > 16)
    return 'В навигации должно быть от 1 до 16 пунктов.'
  for (const [index, item] of items.entries()) {
    const position = `Пункт меню ${index + 1}`
    if ('children' in item) {
      if (!item.label.trim() || item.label.length > 255)
        return `${position}: укажите название до 255 символов.`
      if (item.children.length < 1 || item.children.length > 16)
        return `${position}: добавьте от 1 до 16 подпунктов.`
      for (const [childIndex, child] of item.children.entries()) {
        const error = linkError(
          child,
          `${position}, подпункт ${childIndex + 1}`,
        )
        if (error) return error
      }
    } else {
      const error = linkError(item, position)
      if (error) return error
    }
  }
  return ''
}
