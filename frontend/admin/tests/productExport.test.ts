import { afterEach, expect, test, vi } from 'vitest'
import { getProductExport } from '../src/features/products/services/products'

afterEach(() => vi.unstubAllGlobals())

test('export posts the SKU list as JSON and preserves leading zeros', async () => {
  const fetch = vi.fn().mockResolvedValue(
    new Response('xlsx', {
      headers: {
        'Content-Disposition': 'attachment; filename="selected.xlsx"',
      },
    }),
  )
  vi.stubGlobal('fetch', fetch)
  const result = await getProductExport({}, ['00123', 'ABC-7'])
  const request = fetch.mock.calls.find(([url]) =>
    String(url).includes('/products/export'),
  )!
  expect(request[1].method).toBe('POST')
  expect(JSON.parse(request[1].body)).toEqual({ skus: ['00123', 'ABC-7'] })
  expect(result.filename).toBe('selected.xlsx')
  expect(await result.blob.text()).toBe('xlsx')
})

test('all-products export sends no filters or SKU restriction', async () => {
  const fetch = vi.fn().mockResolvedValue(new Response('xlsx'))
  vi.stubGlobal('fetch', fetch)
  await getProductExport()
  expect(String(fetch.mock.calls[0]![0])).toMatch(/\/admin\/products\/export$/)
})

test('export exposes all field errors so every unknown SKU can be corrected', async () => {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockImplementation(async (url: string) =>
      String(url).includes('/sanctum/')
        ? new Response(null, { status: 204 })
        : new Response(
            JSON.stringify({
              error: {
                message: 'Ошибка проверки.',
                details: {
                  'skus.0': ['SKU AAA не найден.'],
                  'skus.1': ['SKU BBB не найден.'],
                },
              },
            }),
            { status: 422 },
          ),
    ),
  )
  await expect(getProductExport({}, ['AAA', 'BBB'])).rejects.toThrow(
    'SKU AAA не найден. SKU BBB не найден.',
  )
})
