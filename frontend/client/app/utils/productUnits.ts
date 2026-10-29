import type { CatalogProduct } from '~/types/contentPage'
const labels: Record<CatalogProduct['unit'], string> = {
  piece: 'шт.',
  square_meter: 'м²',
  linear_meter: 'пог. м',
  package: 'уп.',
  kilogram: 'кг',
  liter: 'л',
  set: 'компл.',
}
export function productUnitLabel(unit: CatalogProduct['unit']): string {
  return labels[unit]
}
/** Display estimate only. Cart and order totals always come from the server. */
export function estimateProductTotal(
  price: string | null,
  quantity: number,
): string | null {
  if (
    !price ||
    !/^\d+(?:\.\d{1,2})?$/.test(price) ||
    !Number.isInteger(quantity) ||
    quantity < 1 ||
    quantity > 9999
  )
    return null
  const [whole, fraction = ''] = price.split('.')
  const cents =
    (BigInt(whole!) * 100n + BigInt(fraction.padEnd(2, '0'))) * BigInt(quantity)
  return (
    (cents / 100n).toString() + '.' + (cents % 100n).toString().padStart(2, '0')
  )
}
