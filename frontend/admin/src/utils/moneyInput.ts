const groupingSpace = '\u00a0'

/** Keep decimal strings exact; never round editable amounts through Number. */
export function normalizeMoneyInput(value: string): string | null {
  const raw = value
    .trim()
    .replace(/₽$/, '')
    .replace(/[\s\u00a0\u202f]/g, '')
    .replace(',', '.')
  if (!raw) return ''
  if (!/^(?:\d+(?:\.\d{0,2})?|\.\d{0,2})$/.test(raw)) return null
  return raw.replace(/^0+(?=\d)/, '')
}

export function formatMoneyInput(value: string): string {
  const raw = normalizeMoneyInput(value)
  if (raw === null) return value
  const [integer = '', fraction] = raw.split('.')
  const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, groupingSpace)
  return fraction === undefined ? grouped : `${grouped},${fraction}`
}

export function commitMoneyInput(value: string): string | null {
  const raw = normalizeMoneyInput(value)
  if (raw === null || raw === '') return raw
  const [integer, fraction = ''] = raw.split('.')
  return `${integer || '0'}.${fraction.padEnd(2, '0')}`
}

export function moneyInputCaret(
  value: string,
  meaningfulCharacters: number,
): number {
  if (meaningfulCharacters <= 0) return 0
  let count = 0
  for (let index = 0; index < value.length; index++) {
    if (/[\d.,]/.test(value[index] ?? '')) count++
    if (count === meaningfulCharacters) return index + 1
  }
  return value.length
}
