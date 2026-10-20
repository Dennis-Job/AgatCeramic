/** National digits only; callers validating external text must use normalizePhoneInput. */
export function phoneNationalDigits(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (
    value.trim().startsWith('+7') ||
    (digits.length === 11 && /^[78]/.test(digits))
  ) {
    return digits.slice(1)
  }
  return digits
}

/** Partial canonical values are permitted during editing; invalid paste is rejected. */
export function normalizePhoneInput(value: string): string | null {
  const text = value.trim()
  if (!/^[+\d\s()-]*$/.test(text)) return null
  if (
    text.includes('+') &&
    (!text.startsWith('+7') || text.lastIndexOf('+') !== 0)
  )
    return null
  const national = phoneNationalDigits(text)
  if (national.length > 10) return null
  return national ? `+7${national}` : ''
}

export function formatPhoneInput(value: string): string {
  const normalized = normalizePhoneInput(value)
  if (normalized === null) return value
  const national = phoneNationalDigits(normalized)
  if (!national) return '+7'
  let display = `+7 (${national.slice(0, 3)}`
  if (national.length >= 3) display += ')'
  if (national.length > 3) display += ` ${national.slice(3, 6)}`
  if (national.length > 6) display += `-${national.slice(6, 8)}`
  if (national.length > 8) display += `-${national.slice(8, 10)}`
  return display
}

/** Preserve ambiguous legacy data instead of silently displaying a different number. */
export function formatPhone(value?: string | null): string {
  if (!value) return ''
  const normalized = normalizePhoneInput(value)
  return normalized && /^\+7\d{10}$/.test(normalized)
    ? formatPhoneInput(normalized)
    : value
}

export function phoneInputCaret(
  display: string,
  nationalDigitCount: number,
): number {
  if (nationalDigitCount <= 0) return Math.min(4, display.length)
  let count = 0
  for (let index = 2; index < display.length; index++) {
    if (/\d/.test(display[index]!) && ++count === nationalDigitCount)
      return index + 1
  }
  return display.length
}

export function editPhoneInput(
  display: string,
  selectionStart: number,
  selectionEnd: number,
  inputType: string,
  data = '',
): { value: string; display: string; caret: number } {
  const national = phoneNationalDigits(display)
  let start = display
    .slice(2, Math.max(2, selectionStart))
    .replace(/\D/g, '').length
  let end = display
    .slice(2, Math.max(2, selectionEnd))
    .replace(/\D/g, '').length
  let inserted = ''
  if (inputType.startsWith('insert')) {
    const normalized = normalizePhoneInput(data)
    if (normalized === null) {
      return {
        value: normalizePhoneInput(display) ?? display,
        display,
        caret: selectionStart,
      }
    }
    inserted = phoneNationalDigits(normalized)
  } else if (start === end) {
    if (inputType === 'deleteContentBackward') start = Math.max(0, start - 1)
    else if (inputType === 'deleteContentForward')
      end = Math.min(national.length, end + 1)
  }
  const available = Math.max(0, 10 - (national.length - (end - start)))
  inserted = inserted.slice(0, available)
  const digits = national.slice(0, start) + inserted + national.slice(end)
  const value = digits ? `+7${digits}` : ''
  const nextDisplay = formatPhoneInput(value)
  return {
    value,
    display: nextDisplay,
    caret: phoneInputCaret(nextDisplay, start + inserted.length),
  }
}
