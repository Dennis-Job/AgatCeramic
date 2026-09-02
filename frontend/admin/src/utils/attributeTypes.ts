import type { AttributeType } from '../services/attributes'

export const attributeTypeOptions: Array<{ value: AttributeType; label: string }> = [
  { value: 'string', label: 'Строка' },
  { value: 'text', label: 'Многострочный текст' },
  { value: 'integer', label: 'Целое число' },
  { value: 'decimal', label: 'Десятичное число' },
  { value: 'boolean', label: 'Да / нет' },
  { value: 'select', label: 'Список' },
  { value: 'multiselect', label: 'Множественный список' },
  { value: 'date', label: 'Дата' },
]

const attributeTypeLabels: Record<AttributeType, string> = Object.fromEntries(
  attributeTypeOptions.map(option => [option.value, option.label]),
) as Record<AttributeType, string>

export function attributeTypeLabel(type: AttributeType): string {
  return attributeTypeLabels[type]
}
