import type { CollectionPayload } from '../types/collection.types'
export function emptyCollection(): CollectionPayload {
  return {
    brand_id: null,
    name: '',
    slug: '',
    headline: '',
    description: '',
    image_id: null,
    is_active: true,
  }
}
export function validateCollection(payload: CollectionPayload): string {
  if (!payload.brand_id) return 'Выберите бренд коллекции.'
  if (!payload.name.trim()) return 'Укажите название коллекции.'
  if (payload.name.length > 255 || (payload.headline?.length ?? 0) > 255)
    return 'Название и заголовок должны содержать не более 255 символов.'
  if ((payload.description?.length ?? 0) > 10000)
    return 'Описание должно содержать не более 10000 символов.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(payload.slug))
    return 'Технический код может содержать латинские буквы, цифры и дефисы.'
  return ''
}
