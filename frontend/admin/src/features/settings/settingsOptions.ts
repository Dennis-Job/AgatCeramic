export const operatorOptions = [
  { value: 'individual_entrepreneur', label: 'Индивидуальный предприниматель' },
  { value: 'legal_entity', label: 'Юридическое лицо' },
]
export const documentOptions = [
  { value: 'offer', label: 'Оферта' },
  { value: 'privacy_policy', label: 'Политика ПДн' },
  { value: 'consent', label: 'Текст согласия' },
]
export const roleOptions = [
  { value: 'business_owner', label: 'Владелец бизнес-процесса' },
  { value: 'data_protection_officer', label: 'Ответственный за ПДн' },
  { value: 'legal_reviewer', label: 'Юрист / внешний консультант' },
]
export const decisionOptions = [
  { value: 'approved', label: 'Согласовано' },
  { value: 'rejected', label: 'Отклонено' },
]

export function optionLabel(
  options: { value: string; label: string }[],
  value: string,
): string {
  return options.find((option) => option.value === value)?.label ?? value
}
