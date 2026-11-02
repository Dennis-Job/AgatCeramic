import { test } from 'node:test'
import assert from 'node:assert/strict'
import { groupVariantLabels } from '../app/features/product/utils/variantLabels.ts'

test('duplicate color/size choices retain distinct product names without packaging axes', () => {
  const group = {
    axes: [{ slug: 'size' }, { slug: 'color' }],
    products: [
      {
        id: 1,
        name: 'Аллея упаковка 8 шт.',
        axis_values: [
          { slug: 'color', display_value: 'Серый' },
          { slug: 'size', display_value: '30×30' },
        ],
      },
      {
        id: 2,
        name: 'Аллея упаковка 16 шт.',
        axis_values: [
          { slug: 'size', display_value: '30×30' },
          { slug: 'color', display_value: 'Серый' },
        ],
      },
      {
        id: 3,
        name: 'Аллея бежевая',
        axis_values: [
          { slug: 'size', display_value: '30×30' },
          { slug: 'color', display_value: 'Бежевый' },
        ],
      },
    ],
  }
  const labels = groupVariantLabels(group)
  assert.equal(labels.get(1), '30×30 · Серый · Аллея упаковка 8 шт.')
  assert.equal(labels.get(2), '30×30 · Серый · Аллея упаковка 16 шт.')
  assert.equal(labels.get(3), '30×30 · Бежевый')
  assert.equal(groupVariantLabels(null).size, 0)
})

test('identical axes and names are disambiguated by their sellable SKU', () => {
  const group = {
    axes: [{ slug: 'color' }],
    products: [
      {
        id: 1,
        name: 'Аллея',
        sku: 'PACK-8',
        axis_values: [{ slug: 'color', display_value: 'Серый' }],
      },
      {
        id: 2,
        name: 'Аллея',
        sku: 'PACK-16',
        axis_values: [{ slug: 'color', display_value: 'Серый' }],
      },
    ],
  }
  const labels = groupVariantLabels(group)
  assert.equal(labels.get(1), 'Серый · Аллея · Артикул PACK-8')
  assert.equal(labels.get(2), 'Серый · Аллея · Артикул PACK-16')
})
