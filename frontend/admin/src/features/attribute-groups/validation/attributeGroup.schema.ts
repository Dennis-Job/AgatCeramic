import type { AttributeGroupPayload } from '../types/attributeGroup.types'
export function emptyAttributeGroup(): AttributeGroupPayload { return { name: '', slug: '', description: '', sort_order: 0 } }

export function validateAttributeGroup(payload: AttributeGroupPayload): string {
  if (!payload.name.trim()) return 'Укажите название группы.'
  if (!payload.slug.trim()) return 'Укажите технический код группы.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(payload.slug)) return 'Технический код может содержать латинские буквы, цифры и дефисы.'
  if (!Number.isInteger(payload.sort_order) || payload.sort_order < 0) return 'Порядок сортировки должен быть неотрицательным целым числом.'
  return ''
}
