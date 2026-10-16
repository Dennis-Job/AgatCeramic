import type { AuditDetail, AuditLog } from '../types/auditLog.types'
import { auditActionName, auditFieldLabels } from './auditActions'

const timeZone = 'Europe/Moscow'
const dayFormat = new Intl.DateTimeFormat('en-CA', {
  timeZone,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})
const dateFormat = new Intl.DateTimeFormat('ru-RU', {
  timeZone,
  day: 'numeric',
  month: 'long',
})
const timeFormat = new Intl.DateTimeFormat('ru-RU', {
  timeZone,
  hour: '2-digit',
  minute: '2-digit',
})

function timestamp(log: AuditLog): number {
  return log.occurred_at ? Date.parse(log.occurred_at) : NaN
}
function dayKey(date: Date): string {
  const parts = dayFormat.formatToParts(date)
  return ['year', 'month', 'day']
    .map((type) => parts.find((part) => part.type === type)?.value)
    .join('-')
}
export function eventCount(count: number): string {
  const mod100 = count % 100
  const word =
    mod100 >= 11 && mod100 <= 14
      ? 'событий'
      : count % 10 === 1
        ? 'событие'
        : count % 10 >= 2 && count % 10 <= 4
          ? 'события'
          : 'событий'
  return `${count} ${word} на странице`
}
export function groupAuditLogs(logs: AuditLog[], now = new Date()) {
  const today = dayKey(now)
  // Use the Moscow calendar date rather than subtracting local browser hours.
  const yesterday = dayKey(
    new Date(Date.parse(`${today}T12:00:00+03:00`) - 86400000),
  )
  const groups = new Map<
    string,
    { key: string; title: string; logs: AuditLog[] }
  >()
  const sorted = [...logs].sort((a, b) => {
    const left = timestamp(a),
      right = timestamp(b)
    return (
      (Number.isFinite(right) ? right : -Infinity) -
        (Number.isFinite(left) ? left : -Infinity) || b.id - a.id
    )
  })
  for (const log of sorted) {
    const value = timestamp(log)
    const date = Number.isFinite(value) ? new Date(value) : null
    const key = date ? dayKey(date) : 'unknown'
    let title = 'Дата не указана'
    if (date) {
      const label =
        dateFormat.format(date) +
        (key.slice(0, 4) !== today.slice(0, 4) ? ` ${key.slice(0, 4)}` : '')
      title =
        key === today
          ? `Сегодня, ${label}`
          : key === yesterday
            ? `Вчера, ${label}`
            : label
    }
    if (!groups.has(key)) groups.set(key, { key, title, logs: [] })
    groups.get(key)!.logs.push(log)
  }
  return [...groups.values()]
}

const modules: Record<string, string> = {
  product: 'Товары',
  'product-group': 'Объединения',
  category: 'Категории',
  brand: 'Бренды',
  attribute: 'Характеристики',
  'attribute-group': 'Характеристики',
  order: 'Заказы',
  contact: 'Обращения',
  'admin-user': 'Сотрудники',
  role: 'Роли и права',
  auth: 'Доступ',
  admin: 'Доступ',
  security: 'Безопасность',
  import: 'Импорт',
  'image-import': 'Импорт фото',
  page: 'Контент',
  'home-page': 'Контент',
  'site-appearance': 'Оформление',
  'site-settings': 'Настройки',
  media: 'Медиа',
  banner: 'Контент',
  slider: 'Контент',
  store: 'Магазины',
  'legal-document': 'Документы',
  'compliance-approval': 'Согласования',
}
const entityTypes: Record<string, string> = {
  Product: 'Товар',
  ProductImage: 'Фото товара',
  ProductGroup: 'Объединение',
  Category: 'Категория',
  Brand: 'Бренд',
  Attribute: 'Характеристика',
  AttributeGroup: 'Группа характеристик',
  User: 'Сотрудник',
  Role: 'Роль',
  Order: 'Заказ',
  ContactRequest: 'Обращение',
}

// Keep technical identifiers, filenames and identity details in the disclosure.
// Preview only representative fields already formatted and sanitized by the API.
const previewFields = new Set([
  'Статус',
  'Роли',
  'Активность',
  'Главное фото',
  'Цена',
  'Остаток',
  'Обработано строк',
  'Ошибок',
  'Создано',
  'Обновлено',
  'Заменено',
  'Сумма оплаты',
  'Автоматическое изменение',
  'Операция импорта',
  'Затронуто записей',
])

function detailPreview(details: AuditDetail[]): string {
  const parts: string[] = []
  for (const detail of details) {
    if (!previewFields.has(detail.label)) continue
    const part = `${detail.label}: ${detail.value.length <= 60 ? detail.value : 'в подробностях'}`
    if ([...parts, part].join(' · ').length > 140) continue
    parts.push(part)
    if (parts.length === 3) break
  }
  return parts.join(' · ')
}
export function presentAuditLog(log: AuditLog) {
  const summary = log.details.find(
    (detail) => detail.label === 'Действие',
  )?.value
  const separator = summary?.indexOf(': ') ?? -1
  const backendTitle = separator >= 0 ? summary!.slice(0, separator) : summary
  const title =
    backendTitle && backendTitle !== log.action
      ? backendTitle
      : auditActionName(log.action)
  const sku = log.details.find((detail) => detail.label === 'SKU')?.value
  const order = log.details.find(
    (detail) => detail.label === 'Номер заказа',
  )?.value
  const summaryObject = separator >= 0 ? summary!.slice(separator + 2) : ''
  const object =
    log.entity?.name ||
    (summaryObject && !sku ? summaryObject : '') ||
    (order ? `Заказ №${order}` : '') ||
    (log.entity
      ? `${entityTypes[log.entity.type] ?? log.entity.type} #${log.entity.id ?? '—'}`
      : '')
  const actor = log.actor
    ? log.actor.name.trim() ||
      (log.actor.id ? `Сотрудник #${log.actor.id}` : 'Сотрудник')
    : 'Система'
  const initials = log.actor
    ? actor
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => [...part][0])
        .join('')
        .toLocaleUpperCase('ru-RU')
    : ''
  const changes: { label: string; before: string; after: string }[] = []
  const details: AuditDetail[] = []
  const changeLabels = new Set<string>()
  const metadataChanges = log.metadata?.changes
  if (
    metadataChanges &&
    typeof metadataChanges === 'object' &&
    !Array.isArray(metadataChanges)
  ) {
    for (const [field, value] of Object.entries(metadataChanges)) {
      if (
        value &&
        typeof value === 'object' &&
        'before' in value &&
        'after' in value
      )
        changeLabels.add(auditFieldLabels[field] ?? field)
    }
  }
  if (
    log.metadata &&
    ('from_status' in log.metadata || 'to_status' in log.metadata)
  )
    changeLabels.add(
      log.action === 'contact.status-changed'
        ? 'Статус обращения'
        : 'Статус заказа',
    )
  if (
    log.metadata &&
    ('from_payment_status' in log.metadata ||
      'to_payment_status' in log.metadata)
  )
    changeLabels.add('Статус оплаты')
  if (
    log.metadata &&
    ('from_assignee_id' in log.metadata || 'to_assignee_id' in log.metadata)
  )
    changeLabels.add('Ответственный (ID)')
  for (const detail of log.details) {
    if (detail.label === 'Действие') continue
    const parts = detail.value.split(' → ')
    if (changeLabels.has(detail.label) && parts.length === 2) {
      changes.push({ label: detail.label, before: parts[0]!, after: parts[1]! })
    } else if (!['SKU', 'Номер заказа'].includes(detail.label))
      details.push(detail)
  }
  if (
    log.entity?.email &&
    !details.some((detail) => detail.value === log.entity!.email)
  )
    details.push({ label: 'Email', value: log.entity.email })
  const first = changes[0]
  const preview = first
    ? `${first.label}: ${first.before} → ${first.after}`
    : detailPreview(details)
  const compactPreview =
    preview.length <= 140
      ? preview
      : `Изменённые поля: ${changes.map((change) => change.label).join(', ')}`
  return {
    title,
    actor,
    initials,
    object,
    sku,
    changes,
    details,
    module: modules[log.action.split('.')[0]!] ?? 'Админка',
    preview:
      compactPreview.length <= 140
        ? compactPreview
        : `Изменено полей: ${changes.length}`,
    additionalChanges:
      changes.length > 1 && preview.length <= 140 ? changes.length - 1 : 0,
    time: Number.isFinite(timestamp(log))
      ? timeFormat.format(new Date(timestamp(log)))
      : '—',
  }
}
