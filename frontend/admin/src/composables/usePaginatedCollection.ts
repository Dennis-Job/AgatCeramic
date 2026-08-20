import { ref, type Ref } from 'vue'
import type { PaginatedResponse, PaginationMeta } from '../services/pagination'

type PageLoader<T, TResult extends PaginatedResponse<T>> = (page: number) => Promise<TResult>

export type PaginatedCollection<T> = {
  items: Ref<T[]>
  pagination: Ref<PaginationMeta | null>
  error: Ref<string>
  loading: Ref<boolean>
  load: <TResult extends PaginatedResponse<T>>(page: number, loader: PageLoader<T, TResult>) => Promise<TResult | null>
  reloadAfterDeletion: <TResult extends PaginatedResponse<T>>(loader: PageLoader<T, TResult>) => Promise<TResult | null>
}

export function usePaginatedCollection<T>(fallbackError: string): PaginatedCollection<T> {
  const items = ref<T[]>([]) as Ref<T[]>
  const pagination = ref<PaginationMeta | null>(null)
  const error = ref('')
  const loading = ref(false)
  let latestRequest = 0

  async function load<TResult extends PaginatedResponse<T>>(page: number, loader: PageLoader<T, TResult>): Promise<TResult | null> {
    const request = ++latestRequest
    loading.value = true
    error.value = ''

    try {
      const response = await loader(page)
      if (request !== latestRequest) return null

      items.value = response.data
      pagination.value = response.meta

      return response
    } catch (reason) {
      if (request === latestRequest) error.value = reason instanceof Error ? reason.message : fallbackError
      return null
    } finally {
      if (request === latestRequest) loading.value = false
    }
  }

  async function reloadAfterDeletion<TResult extends PaginatedResponse<T>>(loader: PageLoader<T, TResult>): Promise<TResult | null> {
    const currentPage = pagination.value?.current_page ?? 1
    const targetPage = items.value.length <= 1 ? Math.max(1, currentPage - 1) : currentPage
    const response = await load(targetPage, loader)
    if (!response) return null

    const lastPage = Math.max(1, response.meta.last_page)
    const pageIsInvalid = response.meta.current_page > lastPage || (response.data.length === 0 && response.meta.current_page > 1)

    return pageIsInvalid ? load(lastPage, loader) : response
  }

  return { items, pagination, error, loading, load, reloadAfterDeletion }
}
