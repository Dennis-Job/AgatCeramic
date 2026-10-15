export function parseExportSkus(value: string): string[] {
  return [
    ...new Set(
      value
        .split(',')
        .map((sku) => sku.trim())
        .filter(Boolean),
    ),
  ]
}

export function validateExportSkus(skus: readonly string[]): string {
  if (!skus.length) return 'Укажите хотя бы один SKU через запятую.'
  if (skus.length > 1000) return 'За один раз можно скачать не более 1000 SKU.'
  if (skus.some((sku) => sku.length > 255))
    return 'Каждый SKU должен содержать не более 255 символов.'
  return ''
}
