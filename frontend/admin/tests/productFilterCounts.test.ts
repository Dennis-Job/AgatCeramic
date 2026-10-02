import { defineComponent, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, test, vi } from 'vitest'
import { getProducts } from '../src/features/products/services/products'
import { useProductFilterCounts } from '../src/features/products/composables/useProductFilterCounts'
import type { ProductFilters } from '../src/features/products/types/product.types'

vi.mock('../src/features/products/services/products', () => ({
  getProducts: vi.fn(),
}))
const products = vi.mocked(getProducts)
const wrappers: Array<ReturnType<typeof mount>> = []

function response(total: number) {
  return {
    data: [],
    meta: { total, current_page: 1, last_page: 1, per_page: 1 },
  }
}

function setup() {
  const filters = ref<ProductFilters>({})
  let state!: ReturnType<typeof useProductFilterCounts>
  wrappers.push(
    mount(
      defineComponent({
        setup() {
          state = useProductFilterCounts(() => ({
            search: filters.value.search,
            category_id: filters.value.category_id,
            brand_id: filters.value.brand_id,
          }))
          return () => null
        },
      }),
    ),
  )
  return { filters, state }
}

afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.useRealTimers()
  vi.resetAllMocks()
})

describe('product filter totals', () => {
  test('uses API totals beyond the current page and ignores the status selection', async () => {
    vi.useFakeTimers()
    products.mockImplementation(async (filters) =>
      response(
        filters?.is_active === true
          ? 137
          : filters?.is_active === false
            ? 21
            : filters?.is_on_sale
              ? 34
              : 124,
      ),
    )
    const { filters, state } = setup()
    await vi.advanceTimersByTimeAsync(0)
    expect(state.counts.value).toEqual({
      active: 137,
      hidden: 21,
      sale: 34,
      regular: 124,
    })
    filters.value = {
      category_id: 2,
      brand_id: 3,
      search: 'плитка',
      is_active: true,
      is_on_sale: false,
    }
    await vi.advanceTimersByTimeAsync(350)
    for (const [query] of products.mock.calls.slice(-4)) {
      expect(query).toMatchObject({
        category_id: 2,
        brand_id: 3,
        search: 'плитка',
        perPage: 1,
      })
      expect(
        query?.is_active === undefined || query?.is_on_sale === undefined,
      ).toBe(true)
    }
    const calls = products.mock.calls.length
    filters.value.is_active = false
    filters.value.is_on_sale = true
    await vi.advanceTimersByTimeAsync(350)
    expect(products).toHaveBeenCalledTimes(calls)
  })

  test('preserves a real zero and shows unavailable counts independently on failure', async () => {
    vi.useFakeTimers()
    products.mockImplementation(async (filters) => {
      if (filters?.is_active === false) throw new Error('API unavailable')
      return response(0)
    })
    const { state } = setup()
    await vi.advanceTimersByTimeAsync(0)
    expect(state.counts.value).toEqual({
      active: 0,
      hidden: null,
      sale: 0,
      regular: 0,
    })
    expect(state.error.value).toContain('Не удалось обновить')
    expect(state.loading.value).toBe(false)
  })

  test('discards stale responses when the search changes during a request', async () => {
    vi.useFakeTimers()
    let release!: () => void
    const pending = new Promise<void>((resolve) => {
      release = resolve
    })
    products.mockImplementation(async (filters) => {
      if (!filters?.search) await pending
      return response(filters?.search ? 7 : 100)
    })
    const { filters, state } = setup()
    await vi.advanceTimersByTimeAsync(0)
    filters.value.search = 'мрамор'
    await vi.advanceTimersByTimeAsync(350)
    expect(state.counts.value.active).toBe(7)
    release()
    await flushPromises()
    expect(state.counts.value.active).toBe(7)
  })

  test('refreshes totals after a product mutation without a filter change', async () => {
    vi.useFakeTimers()
    products.mockResolvedValue(response(2))
    const { state } = setup()
    await vi.advanceTimersByTimeAsync(0)
    products.mockResolvedValue(response(3))
    await state.refresh()
    expect(state.counts.value.active).toBe(3)
  })
})
