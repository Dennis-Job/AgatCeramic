import type { CategoryPayload } from '../types/category.types'
export function emptyCategory(): CategoryPayload { return { parent_id: null, name: '', slug: '', description: '', is_parent: false, is_active: true, sort_order: 0 } }

export function validateCategory(payload: CategoryPayload): string {
  if (!payload.name.trim()) return 'Укажите название категории.'
  if (!payload.slug.trim()) return 'Укажите технический код категории.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(payload.slug)) return 'Технический код может содержать латинские буквы, цифры и дефисы.'
  if (!Number.isInteger(payload.sort_order) || payload.sort_order < 0) return 'Порядок сортировки должен быть неотрицательным целым числом.'
  return ''
}
