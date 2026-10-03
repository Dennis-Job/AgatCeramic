const rubles = new Intl.NumberFormat('ru-RU', {
  style: 'currency',
  currency: 'RUB',
  useGrouping: true,
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

/** Display-only formatting; keep API values and editable amounts unchanged. */
export function formatMoney(value: string | number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'string' && !value.trim()) return '—'
  const amount = Number(value)
  return Number.isFinite(amount) ? rubles.format(amount) : '—'
}
