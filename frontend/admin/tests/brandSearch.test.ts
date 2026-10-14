import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { useBrandCatalog } from '../src/features/brands/composables/useBrandCatalog'
import type { Brand } from '../src/features/brands/types/brand.types'

const api = vi.hoisted(() => ({
  getBrands: vi.fn(),
  deleteBrand: vi.fn(),
  saveBrand: vi.fn(),
}))
vi.mock('../src/features/brands/services/brands', () => api)
const brand: Brand = {
  id: 1,
  name: 'Kerama Marazzi',
  slug: 'kerama-marazzi',
  description: null,
  country_code: null,
  logo_id: null,
  logo: null,
  documents: [],
  is_active: true,
  created_at: '2026-10-14T10:00:00Z',
  updated_at: '2026-10-14T10:00:00Z',
}
const response = (page = 1, data = [brand]) => ({
  data,
  meta: { current_page: page, last_page: 2, total: 30, per_page: 25 },
})

beforeEach(() => {
  vi.useFakeTimers()
  vi.resetAllMocks()
  api.getBrands.mockResolvedValue(response())
})
afterEach(() => {
  vi.useRealTimers()
})

function setup() {
  let catalog!: ReturnType<typeof useBrandCatalog>
  const wrapper = mount(
    defineComponent({
      setup() {
        catalog = useBrandCatalog()
        return () => h('div')
      },
    }),
  )
  return { catalog, wrapper }
}

test('search resets pagination, trims input and keeps query on subsequent pages and reload', async () => {
  const { catalog, wrapper } = setup()
  api.getBrands.mockResolvedValueOnce(response(2))
  await catalog.load(2)
  catalog.search.value = '  MARAZZI  '
  await vi.advanceTimersByTimeAsync(349)
  expect(api.getBrands).toHaveBeenCalledTimes(1)
  expect(catalog.loading.value).toBe(true)
  await vi.advanceTimersByTimeAsync(1)
  expect(api.getBrands).toHaveBeenLastCalledWith({ page: 1, search: 'MARAZZI' })
  await catalog.load(2)
  expect(api.getBrands).toHaveBeenLastCalledWith({ page: 2, search: 'MARAZZI' })
  await catalog.loadAfterDeletion()
  expect(api.getBrands.mock.calls.at(-1)?.[0].search).toBe('MARAZZI')
  catalog.search.value = ''
  await vi.advanceTimersByTimeAsync(350)
  expect(api.getBrands).toHaveBeenLastCalledWith({ page: 1, search: '' })
  wrapper.unmount()
})

test('an older search response cannot replace the latest results', async () => {
  const { catalog, wrapper } = setup()
  let finishOld!: (value: ReturnType<typeof response>) => void
  api.getBrands.mockReturnValueOnce(
    new Promise((resolve) => {
      finishOld = resolve
    }),
  )
  catalog.search.value = 'old'
  await vi.advanceTimersByTimeAsync(350)
  catalog.search.value = 'new'
  api.getBrands.mockResolvedValueOnce(response(1, [{ ...brand, name: 'new' }]))
  await vi.advanceTimersByTimeAsync(350)
  finishOld(response(1, [{ ...brand, name: 'old' }]))
  await Promise.resolve()
  expect(catalog.brands.value[0]?.name).toBe('new')
  expect(catalog.loading.value).toBe(false)
  wrapper.unmount()
})

test('unmount cancels scheduled search requests', async () => {
  const { catalog, wrapper } = setup()
  catalog.search.value = 'pending'
  wrapper.unmount()
  await vi.advanceTimersByTimeAsync(350)
  expect(api.getBrands).not.toHaveBeenCalled()
})

test('retrying a failed new search requests its first page rather than the previous query page', async () => {
  const { catalog, wrapper } = setup()
  api.getBrands.mockResolvedValueOnce(response(2))
  await catalog.load(2)
  api.getBrands.mockRejectedValueOnce(new Error('Search failed'))
  catalog.search.value = 'new search'
  await vi.advanceTimersByTimeAsync(350)
  expect(catalog.error.value).toBe('Search failed')
  await catalog.load()
  expect(api.getBrands).toHaveBeenLastCalledWith({
    page: 1,
    search: 'new search',
  })
  wrapper.unmount()
})
