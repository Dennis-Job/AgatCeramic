export function currentMoscowMonth(): string {
  const parts = new Intl.DateTimeFormat('en', {
    timeZone: 'Europe/Moscow',
    year: 'numeric',
    month: '2-digit',
  }).formatToParts(new Date())
  return `${parts.find((part) => part.type === 'year')!.value.padStart(4, '0')}-${parts.find((part) => part.type === 'month')!.value}`
}

export function formatMonth(value: string): string {
  if (!/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/.test(value)) return ''
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: 'UTC',
    month: 'long',
    year: 'numeric',
  })
    .format(new Date(`${value}-01T12:00:00Z`))
    .replace(/\s*г\.$/, '')
}
