import type { GroupProduct, ProductDetail } from '../types/product'

export function groupVariantChoices(
  group: ProductDetail['group'],
  currentProduct: GroupProduct,
) {
  if (!group) return { axes: [], alternateOptions: [] }
  const currentId = currentProduct.id
  const current = group.products.find((product) => product.id === currentId)
  const products = current
    ? group.products
    : [...group.products, currentProduct]
  const labels = groupVariantLabels({ ...group, products })
  const optionForProduct = (product: GroupProduct) => ({
    product,
    label: labels.get(product.id) ?? product.name,
  })
  if (!group.axes.length)
    return { axes: [], alternateOptions: products.map(optionForProduct) }

  const keys = (product: GroupProduct) =>
    group.axes.map(({ slug }) => {
      const value = product.axis_values.find(
        (value) => value.slug === slug,
      )?.value
      return JSON.stringify(
        Array.isArray(value) ? [...value].sort() : (value ?? null),
      )
    })
  const currentKeys = keys(current ?? currentProduct)
  const choices = products.map((product) => ({ product, keys: keys(product) }))
  const axes = group.axes.map((axis, index) => {
    const compatible = choices
      .filter(
        (choice) =>
          choice.product.id === currentId ||
          (choice.keys[index] !== currentKeys[index] &&
            choice.keys.every(
              (key, other) => other === index || key === currentKeys[other],
            )),
      )
      .map((choice) => ({
        product: choice.product,
        label:
          choice.product.axis_values.find((value) => value.slug === axis.slug)
            ?.display_value || 'Не указано',
      }))
    const counts = new Map<string, number>()
    for (const { label } of compatible)
      counts.set(label, (counts.get(label) ?? 0) + 1)
    const options = compatible.map((option) =>
      (counts.get(option.label) ?? 0) > 1
        ? optionForProduct(option.product)
        : option,
    )
    return { ...axis, options }
  })
  const duplicates = choices.filter((choice) =>
    choice.keys.every((key, index) => key === currentKeys[index]),
  )
  return {
    axes,
    alternateOptions:
      duplicates.length > 1
        ? duplicates.map(({ product }) => optionForProduct(product))
        : [],
  }
}

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
