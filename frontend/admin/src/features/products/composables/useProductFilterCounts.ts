import { onBeforeUnmount, ref, watch } from 'vue'
import { getProductFilterCounts } from '../services/productFilterCounts'
import type {
  ProductFilterCounts,
  ProductFilters,
} from '../types/product.types'

const emptyCounts = (): ProductFilterCounts => ({
  active: null,
  hidden: null,
  sale: null,
  regular: null,
})

export function useProductFilterCounts(
  filters: () => Pick<ProductFilters, 'search' | 'category_id' | 'brand_id'>,
) {
  const counts = ref(emptyCounts())
  const loading = ref(false)
  const error = ref('')
  let requestId = 0
  let timer: ReturnType<typeof setTimeout> | undefined

  async function refresh(): Promise<void> {
    if (timer) clearTimeout(timer)
    const request = ++requestId
    loading.value = true
    error.value = ''
    counts.value = emptyCounts()
    const response = await getProductFilterCounts(filters())
    if (request !== requestId) return
    counts.value = response
    loading.value = false
    if (Object.values(response).some((count) => count === null))
      error.value = 'Не удалось обновить некоторые счётчики товаров.'
  }

  watch(
    () => JSON.stringify(filters()),
    (next, previous) => {
      if (timer) clearTimeout(timer)
      ++requestId
      counts.value = emptyCounts()
      loading.value = true
      error.value = ''
      const searchChanged =
        previous !== undefined &&
        JSON.parse(next).search !== JSON.parse(previous).search
      timer = setTimeout(() => void refresh(), searchChanged ? 350 : 0)
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    ++requestId
    if (timer) clearTimeout(timer)
  })

  return { counts, loading, error, refresh }
}
