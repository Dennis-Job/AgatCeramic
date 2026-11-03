import { test } from 'node:test'
import assert from 'node:assert/strict'
import * as variants from '../app/features/product/utils/variantLabels.ts'

const axisValue = (slug, value) => ({ slug, value, display_value: value })
const product = (id, color, size, finish) => ({
  id,
  name: 'Аллея',
  sku: `TEST-${id}`,
  axis_values: [
    axisValue('color', color),
    axisValue('size', size),
    axisValue('finish', finish),
  ],
})
const group = {
  axes: [{ slug: 'color' }, { slug: 'size' }, { slug: 'finish' }],
  products: [
    product(1, 'Серый', '300×300', 'Матовый'),
    product(2, 'Песочный', '300×300', 'Матовый'),
    product(3, 'Серый', '600×300', 'Матовый'),
    product(4, 'Песочный', '600×300', 'Глянцевый'),
    product(5, 'Серый', '600×300', 'Матовый'),
  ],
}

test('each axis preserves every other raw attribute value', () => {
  const { axes, alternateOptions } = variants.groupVariantChoices(
    group,
    group.products[2],
  )
  assert.deepEqual(
    axes.map(({ options }) => options.map(({ product }) => product.id)),
    [[3], [1, 3], [3]],
  )
  assert.deepEqual(
    alternateOptions.map(({ product }) => product.id),
    [3, 5],
  )
  const updated = variants.groupVariantChoices(group, group.products[1])
  assert.deepEqual(
    updated.axes.map(({ options }) => options.map(({ product }) => product.id)),
    [[1, 2], [2], [2]],
  )
})

test('axis labels use the configured unit from the product attributes', () => {
  const current = {
    ...group.products[0],
    axis_values: group.products[0].axis_values.map((value) => ({
      ...value,
      unit: value.slug === 'size' ? 'мм' : null,
    })),
  }
  const { axes } = variants.groupVariantChoices(group, current)
  assert.deepEqual(
    axes[0].options.map(({ label }) => label),
    ['Серый', 'Песочный'],
  )
  assert.deepEqual(
    axes[1].options.map(({ label }) => label),
    [
      '300×300 мм',
      'Серый · 600×300 мм · Матовый · Аллея · Артикул TEST-3',
      'Серый · 600×300 мм · Матовый · Аллея · Артикул TEST-5',
    ],
  )
})

test('switching to a combination with multiple SKUs leaves the product choice explicit', () => {
  const { axes } = variants.groupVariantChoices(group, group.products[0])
  assert.deepEqual(
    axes[1].options.map(({ product, label }) => [product.id, label]),
    [
      [1, '300×300'],
      [3, 'Серый · 600×300 · Матовый · Аллея · Артикул TEST-3'],
      [5, 'Серый · 600×300 · Матовый · Аллея · Артикул TEST-5'],
    ],
  )
})

test('a paused product outside the public group still constrains sibling choices', () => {
  const paused = product(6, 'Серый', '600×300', 'Матовый')
  const { axes } = variants.groupVariantChoices(group, paused)
  assert.deepEqual(
    axes[0].options.map(({ product }) => product.id),
    [6],
  )
  assert.deepEqual(
    axes[1].options.map(({ product }) => product.id),
    [1, 6],
  )
})

test('identical display labels stay distinguishable and do not match other raw values', () => {
  const gray = product(1, '#808080', '300×300', 'Матовый')
  const silver = product(2, '#B5B5B5', '300×300', 'Матовый')
  const largeSilver = product(3, '#B5B5B5', '600×300', 'Матовый')
  for (const item of [gray, silver, largeSilver])
    item.axis_values[0].display_value = 'Серый'
  const { axes } = variants.groupVariantChoices(
    { ...group, products: [gray, silver, largeSilver] },
    gray,
  )
  assert.deepEqual(
    axes[0].options.map(({ label }) => label),
    [
      'Серый · 300×300 · Матовый · Аллея · Артикул TEST-1',
      'Серый · 300×300 · Матовый · Аллея · Артикул TEST-2',
    ],
  )
  assert.deepEqual(
    axes[1].options.map(({ product }) => product.id),
    [1],
  )
})
