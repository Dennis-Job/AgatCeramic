import type { ProductUnit } from './types/product.types'

const unitLabels: Record<ProductUnit, string> = {
  piece: 'шт.',
  square_meter: 'м²',
  linear_meter: 'пог. м',
  package: 'упак.',
  kilogram: 'кг',
  liter: 'л',
  set: 'компл.',
}

export function productUnitLabel(unit: ProductUnit): string {
  return unitLabels[unit]
}
