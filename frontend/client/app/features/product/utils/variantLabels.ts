interface VariantGroup {
  axes: { slug: string }[]
  products: {
    id: number
    name: string
    sku?: string
    axis_values: { slug: string; display_value: string }[]
  }[]
}

export function groupVariantLabels(
  group: VariantGroup | null,
): Map<number, string> {
  if (!group) return new Map()
  const choices = group.products.map((product) => ({
    product,
    label: group.axes
      .map(
        (axis) =>
          product.axis_values.find((value) => value.slug === axis.slug)
            ?.display_value || '—',
      )
      .join(' · '),
  }))
  const counts = new Map<string, number>()
  for (const { label } of choices)
    counts.set(label, (counts.get(label) ?? 0) + 1)
  const named = choices.map(({ product, label }) => ({
    product,
    label: !label
      ? product.name
      : (counts.get(label) ?? 0) > 1
        ? `${label} · ${product.name}`
        : label,
  }))
  const nameCounts = new Map<string, number>()
  for (const { label } of named)
    nameCounts.set(label, (nameCounts.get(label) ?? 0) + 1)
  return new Map(
    named.map(({ product, label }) => [
      product.id,
      (nameCounts.get(label) ?? 0) > 1 && product.sku
        ? `${label} · Артикул ${product.sku}`
        : label,
    ]),
  )
}
