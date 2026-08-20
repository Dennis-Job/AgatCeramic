import assert from 'node:assert/strict'
import { describe, test } from 'vitest'
import { usePaginatedCollection } from '../src/composables/usePaginatedCollection.ts'
import type { PaginatedResponse } from '../src/services/pagination.ts'

type Item = { id: number }
type Deferred<T> = { promise: Promise<T>; resolve: (value: T) => void }

function deferred<T>(): Deferred<T> {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => { resolve = done })
  return { promise, resolve }
}

function page(currentPage: number, ids: number[], lastPage = currentPage): PaginatedResponse<Item> {
  return {
    data: ids.map((id) => ({ id })),
    meta: { current_page: currentPage, last_page: lastPage, per_page: 15, total: ids.length + (currentPage - 1) * 15 },
  }
}

for (const collection of ['products', 'brands', 'attributes', 'attribute groups']) {
  describe(collection, () => {
    test('ignores a stale response that finishes after a newer page', async () => {
      const list = usePaginatedCollection<Item>('Load failed')
      const first = deferred<PaginatedResponse<Item>>()
      const second = deferred<PaginatedResponse<Item>>()

      const firstLoad = list.load(1, () => first.promise)
      const secondLoad = list.load(2, () => second.promise)
      second.resolve(page(2, [20], 2))
      await secondLoad
      first.resolve(page(1, [10], 2))
      await firstLoad

      assert.deepEqual(list.items.value, [{ id: 20 }])
      assert.equal(list.pagination.value?.current_page, 2)
      assert.equal(list.loading.value, false)
    })

    test('reloads the previous page after deleting its final item', async () => {
      const list = usePaginatedCollection<Item>('Load failed')
      await list.load(3, async () => page(3, [31], 3))
      const requestedPages: number[] = []

      await list.reloadAfterDeletion(async (requestedPage) => {
        requestedPages.push(requestedPage)
        return page(requestedPage, [20], 2)
      })

      assert.deepEqual(requestedPages, [2])
      assert.equal(list.pagination.value?.current_page, 2)
    })

    test('clamps to server last_page when concurrent deletions invalidate the cached page', async () => {
      const list = usePaginatedCollection<Item>('Load failed')
      await list.load(3, async () => page(3, [31, 32], 3))
      const requestedPages: number[] = []

      await list.reloadAfterDeletion(async (requestedPage) => {
        requestedPages.push(requestedPage)
        return requestedPage === 3 ? page(3, [], 2) : page(2, [20], 2)
      })

      assert.deepEqual(requestedPages, [3, 2])
      assert.deepEqual(list.items.value, [{ id: 20 }])
      assert.equal(list.pagination.value?.current_page, 2)
    })
  })
}
