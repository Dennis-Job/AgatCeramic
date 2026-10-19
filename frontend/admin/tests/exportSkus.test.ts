import { expect, test } from 'vitest'
import {
  parseExportSkus,
  validateExportSkus,
} from '../src/features/products/validation/exportSkus'

test('SKU input preserves identifiers while trimming, deduplicating and dropping empty entries', () => {
  expect(parseExportSkus(' 00123, ABC-7, 00123, ,\n 00042 ')).toEqual([
    '00123',
    'ABC-7',
    '00042',
  ])
})

test('empty, oversized and too-long SKU lists are rejected before downloading', () => {
  expect(validateExportSkus(parseExportSkus(' , , '))).not.toBe('')
  expect(
    validateExportSkus(
      Array.from({ length: 1001 }, (_, index) => String(index)),
    ),
  ).not.toBe('')
  expect(validateExportSkus(['a'.repeat(256)])).not.toBe('')
  expect(
    validateExportSkus(
      Array.from({ length: 1000 }, (_, index) => String(index)),
    ),
  ).toBe('')
})
