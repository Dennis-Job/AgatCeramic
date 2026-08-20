export type PaginationMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from?: number | null
  to?: number | null
}

export type PaginatedResponse<T> = { data: T[]; meta: PaginationMeta }

export type PageRequest = { page?: number; perPage?: number }

export function withPage(query: URLSearchParams, request: PageRequest = {}): void {
  if (request.page && request.page > 1) query.set('page', String(request.page))
  if (request.perPage) query.set('per_page', String(request.perPage))
}

export async function loadAllPages<T>(loadPage: (request: PageRequest) => Promise<PaginatedResponse<T>>): Promise<T[]> {
  const firstPage = await loadPage({ page: 1, perPage: 100 })
  const items = [...firstPage.data]

  for (let page = 2; page <= firstPage.meta.last_page; page += 1) {
    items.push(...(await loadPage({ page, perPage: 100 })).data)
  }

  return items
}
