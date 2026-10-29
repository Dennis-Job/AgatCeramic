import type { CartItem } from '../types/cart'

/** Aggregate only server-authoritative line totals, using integer minor units. */
export function cartTotal(items: CartItem[]): string | null {
  let total = 0n
  for (const item of items) {
    if (item.line_total === null || !/^\d+\.\d{2}$/.test(item.line_total))
      return null
    const [whole, fraction] = item.line_total.split('.')
    total += BigInt(whole!) * 100n + BigInt(fraction!)
  }
  return (
    (total / 100n).toString() + '.' + (total % 100n).toString().padStart(2, '0')
  )
}
