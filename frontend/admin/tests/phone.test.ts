import { describe, expect, test } from 'vitest'
import {
  editPhoneInput,
  formatPhone,
  formatPhoneInput,
  normalizePhoneInput,
} from '../src/utils/phone'

describe('Russian telephone contract', () => {
  test.each(['4951204567', '+74951204567', '8 (495) 120-45-67'])(
    'normalizes %s into the API contract',
    (value) => {
      expect(normalizePhoneInput(value)).toBe('+74951204567')
      expect(formatPhone(value)).toBe('+7 (495) 120-45-67')
    },
  )
  test('formats partial input and preserves unsupported legacy values for display', () => {
    expect(normalizePhoneInput('+7')).toBe('')
    expect(formatPhoneInput('')).toBe('+7')
    expect(formatPhoneInput('+74951')).toBe('+7 (495) 1')
    expect(formatPhone(null)).toBe('')
    expect(formatPhone('+49 151 12345678')).toBe('+49 151 12345678')
    expect(normalizePhoneInput('+749512045671')).toBeNull()
    expect(normalizePhoneInput('4951204567,9991112233')).toBeNull()
  })
  test('forward deletion skips formatting and insertion keeps national digits beginning with 7 or 8', () => {
    const result = editPhoneInput(
      '+7 (495) 120-45-67',
      7,
      7,
      'deleteContentForward',
    )
    expect(result.value).toBe('+7495204567')
    expect(result.display).toBe('+7 (495) 204-56-7')
    expect(editPhoneInput('+7', 2, 2, 'insertText', '8').value).toBe('+78')
    expect(editPhoneInput('+7', 2, 2, 'insertText', '7').value).toBe('+77')
  })
  test('ignores extra typed digits and refuses invalid paste', () => {
    const display = '+7 (495) 120-45-67'
    expect(editPhoneInput(display, 18, 18, 'insertText', '9').display).toBe(
      display,
    )
    expect(
      editPhoneInput(display, 0, 18, 'insertFromPaste', '+49 1234567890')
        .display,
    ).toBe(display)
  })
})
