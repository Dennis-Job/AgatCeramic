/** Only literal HEX codes may enter swatch styles; names remain display text. */
export function hexColor(value: string): string | undefined {
  return /^#(?:[\da-f]{3}|[\da-f]{4}|[\da-f]{6}|[\da-f]{8})$/i.test(value)
    ? value
    : undefined
}
