import { defineComponent, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, test, vi } from 'vitest'
import { useProductFilterCounts } from '../src/features/products/composables/useProductFilterCounts'
import { getProductFilterCounts } from '../src/features/products/services/productFilterCounts'
import type { ProductFilters } from '../src/features/products/types/product.types'

vi.mock('../src/features/products/services/productFilterCounts', () => ({
  getProductFilterCounts: vi.fn(),
}))
const getCounts = vi.mocked(getProductFilterCounts)
const wrappers: Array<ReturnType<typeof mount>> = []

function counts(
  active: number | null,
  hidden: number | null,
  sale: number | null,
  regular: number | null,
) {
  return { active, hidden, sale, regular }
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
            is_active: filters.value.is_active,
            is_on_sale: filters.value.is_on_sale,
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
  test('coalesces quick filter changes into one count request', async () => {
    vi.useFakeTimers()
    getCounts.mockResolvedValue(counts(12, 4, 3, 13))
    const { filters } = setup()
    filters.value.category_id = 27
    filters.value.brand_id = 9
    filters.value.is_active = true
    await vi.advanceTimersByTimeAsync(249)
    expect(getCounts).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(getCounts).toHaveBeenCalledTimes(1)
    expect(getCounts).toHaveBeenCalledWith({
      category_id: 27,
      brand_id: 9,
      is_active: true,
      is_on_sale: undefined,
      search: undefined,
    })
  })

  test('requests counts once with all active filters', async () => {
    vi.useFakeTimers()
    getCounts.mockResolvedValue(counts(137, 21, 34, 124))
    const { filters, state } = setup()
    await vi.advanceTimersByTimeAsync(250)
    expect(state.counts.value).toEqual({
      active: 137,
      hidden: 21,
      sale: 34,
      regular: 124,
    })
    expect(getCounts).toHaveBeenCalledTimes(1)
    filters.value = {
      category_id: 2,
      brand_id: 3,
      search: 'плитка',
      is_active: true,
      is_on_sale: false,
    }
    await vi.advanceTimersByTimeAsync(350)
    expect(getCounts).toHaveBeenLastCalledWith({
      category_id: 2,
      brand_id: 3,
      search: 'плитка',
      is_active: true,
      is_on_sale: false,
    })
    const calls = getCounts.mock.calls.length
    filters.value.is_active = false
    await vi.advanceTimersByTimeAsync(250)
    expect(getCounts).toHaveBeenCalledTimes(calls + 1)
    expect(getCounts).toHaveBeenLastCalledWith({
      category_id: 2,
      brand_id: 3,
      search: 'плитка',
      is_active: false,
      is_on_sale: false,
    })
    filters.value.is_on_sale = true
    await vi.advanceTimersByTimeAsync(250)
    expect(getCounts).toHaveBeenCalledTimes(calls + 2)
    expect(getCounts).toHaveBeenLastCalledWith({
      category_id: 2,
      brand_id: 3,
      search: 'плитка',
      is_active: false,
      is_on_sale: true,
    })
  })

  test('preserves zeros and surfaces a count request failure', async () => {
    vi.useFakeTimers()
    getCounts.mockResolvedValue(counts(0, 0, 0, 0))
    const { state } = setup()
    await vi.advanceTimersByTimeAsync(250)
    expect(state.counts.value).toEqual(counts(0, 0, 0, 0))
    getCounts.mockRejectedValue(
      new Error('Слишком много запросов. Повторите попытку позже.'),
    )
    await state.refresh()
    expect(state.counts.value).toEqual(counts(null, null, null, null))
    expect(state.error.value).toContain('Слишком много запросов')
    expect(state.loading.value).toBe(false)
  })

  test('discards stale responses when the search changes during a request', async () => {
    vi.useFakeTimers()
    let release!: () => void
    const pending = new Promise<void>((resolve) => {
      release = resolve
    })
    getCounts.mockImplementation(async (filters) => {
      if (!filters?.search) await pending
      return filters?.search ? counts(7, 0, 2, 5) : counts(100, 10, 20, 90)
    })
    const { filters, state } = setup()
    await vi.advanceTimersByTimeAsync(250)
    filters.value.search = 'мрамор'
    await vi.advanceTimersByTimeAsync(350)
    expect(state.counts.value.active).toBe(7)
    release()
    await flushPromises()
    expect(state.counts.value.active).toBe(7)
  })

  test('refreshes totals after a product mutation without a filter change', async () => {
    vi.useFakeTimers()
    getCounts.mockResolvedValue(counts(2, 1, 1, 2))
    const { state } = setup()
    await vi.advanceTimersByTimeAsync(250)
    getCounts.mockResolvedValue(counts(3, 1, 2, 2))
    await state.refresh()
    expect(state.counts.value.active).toBe(3)
  })
})
