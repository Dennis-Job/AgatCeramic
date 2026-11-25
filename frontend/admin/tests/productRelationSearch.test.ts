import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { useProductCatalogOptions } from '../src/features/products/composables/useProductCatalogOptions'
import { useProductEditor } from '../src/features/products/composables/useProductEditor'
import type { Product } from '../src/features/products/types/product.types'

const api = vi.hoisted(() => ({
  getRelationCandidates: vi.fn(),
  saveProductRelations: vi.fn(),
  getCategoryAttributes: vi.fn(),
  getProductRelations: vi.fn(),
}))
vi.mock('../src/features/products/services/productRelations', () => ({
  getRelationCandidates: api.getRelationCandidates,
  getProductRelations: api.getProductRelations,
  saveProductRelations: api.saveProductRelations,
  ProductRelationRequestError: class extends Error {},
}))
vi.mock('../src/features/products/services/productAttributes', () => ({
  getCategoryAttributes: api.getCategoryAttributes,
  getProductAttributeValues: vi.fn(async () => []),
}))
vi.mock('../src/features/categories/services/categories', () => ({
  getCategoryAttributeGroups: vi.fn(async () => []),
}))
vi.mock('../src/features/products/services/productImages', () => ({
  getAllProductImages: vi.fn(async () => []),
}))
vi.mock('../src/features/products/services/productGroups', () => ({
  getAllProductGroups: vi.fn(async () => []),
}))

const first = { id: 2, sku: '6000034', name: 'Смеситель' } as Product
const second = { id: 3, sku: '5000033', name: 'Клей' } as Product
const product = { id: 1, sku: 'SELF', category_id: 1 } as Product
const wrappers: ReturnType<typeof mount>[] = []

beforeEach(() => {
  vi.resetAllMocks()
  vi.useFakeTimers()
  api.getCategoryAttributes.mockResolvedValue([])
  api.getProductRelations.mockResolvedValue([])
  api.getRelationCandidates.mockResolvedValue([first, second])
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.useRealTimers()
})

function mountEditor() {
  let state!: ReturnType<typeof useProductEditor>
  wrappers.push(
    mount(
      defineComponent({
        setup() {
          state = useProductEditor({
            catalog: useProductCatalogOptions(),
            canManage: ref(true),
            error: ref(''),
            saving: ref(false),
            refreshList: async () => {},
            updateThumbnail: vi.fn(),
          })
          return () => null
        },
      }),
    ),
  )
  return state
}

async function openEditor() {
  const state = mountEditor()
  await state.open(product)
  return state
}

test('searches the latest trimmed SKU automatically without treating search as a save', async () => {
  const { relations: state, saving } = await openEditor()
  state.relationSearch.value = '60000'
  await vi.advanceTimersByTimeAsync(100)
  state.relationSearch.value = ' 6000034 '
  await vi.advanceTimersByTimeAsync(350)
  await flushPromises()
  expect(api.getRelationCandidates).toHaveBeenCalledTimes(1)
  expect(api.getRelationCandidates).toHaveBeenCalledWith(1, '6000034', 20)
  expect(saving.value).toBe(false)
})

test('starts with an empty lookup and never searches or adds a random product', async () => {
  const { relations: state } = await openEditor()
  await state.searchRelations()
  state.addRelation('related')
  expect(api.getRelationCandidates).not.toHaveBeenCalled()
  expect(state.relationSearchResult.value).toBeNull()
  expect(state.relations.value).toEqual([])
})

test('adds the exact SKU with an explicit type, clears lookup and preserves several drafts', async () => {
  const { relations: state } = await openEditor()
  state.relationSearch.value = first.sku
  await state.searchRelations()
  expect(state.relationSearchResult.value?.id).toBe(2)
  expect(state.addRelation('recommended')).toBe(true)
  expect(state.relationSearch.value).toBe('')
  expect(state.relationSearchResult.value).toBeNull()
  expect(state.relations.value[0]?.type).toBe('recommended')
  expect(state.relations.value.map((row) => row.related_product_id)).toEqual([
    '2',
  ])
  api.getRelationCandidates.mockResolvedValue([second])
  state.relationSearch.value = '5000033'
  await vi.advanceTimersByTimeAsync(350)
  await flushPromises()
  state.addRelation('related')
  state.addRelation('related')
  expect(state.relations.value.map((row) => row.related_product_id)).toEqual([
    '2',
    '3',
  ])
  expect(state.relationProduct('2')?.sku).toBe('6000034')
  expect(api.saveProductRelations).not.toHaveBeenCalled()
})

test('ignores an older response and disables stale additions while a new query is pending', async () => {
  const { relations: state } = await openEditor()
  state.relationSearch.value = first.sku
  let resolveOld!: (products: Product[]) => void
  api.getRelationCandidates.mockReturnValueOnce(
    new Promise<Product[]>((resolve) => {
      resolveOld = resolve
    }),
  )
  const oldRequest = state.searchRelations()
  state.relationSearch.value = '5000033'
  state.addRelation('related')
  expect(state.relations.value).toEqual([])
  api.getRelationCandidates.mockResolvedValue([second])
  await state.searchRelations()
  resolveOld([first])
  await oldRequest
  expect(state.relationSearchResult.value?.id).toBe(3)
  state.addRelation('related')
  expect(state.relations.value[0]?.related_product_id).toBe('3')
})

test('search errors stay local, disable adding stale results and allow retry', async () => {
  const { relations: state, error } = await openEditor()
  state.relationSearch.value = first.sku
  await state.searchRelations()
  api.getRelationCandidates.mockRejectedValueOnce(new Error('Поиск недоступен'))
  await state.searchRelations()
  state.addRelation('related')
  expect(state.relations.value).toEqual([])
  expect(state.relationSearchError.value).toBe('Поиск недоступен')
  expect(error.value).toBe('')
  await state.searchRelations()
  expect(state.relationSearchError.value).toBe('')
  expect(state.relationSearchResult.value?.id).toBe(2)
})

test('closing the editor invalidates pending requests and debounce timers', async () => {
  const { relations: state, close } = await openEditor()
  state.relationSearch.value = first.sku
  let resolve!: (products: Product[]) => void
  api.getRelationCandidates.mockReturnValueOnce(
    new Promise<Product[]>((done) => {
      resolve = done
    }),
  )
  const request = state.searchRelations()
  close()
  resolve([first])
  await request
  expect(state.relationSearchResult.value).toBeNull()
  state.relationSearch.value = '6000034'
  await vi.advanceTimersByTimeAsync(500)
  expect(api.getRelationCandidates).toHaveBeenCalledTimes(1)
})

test('initial details cannot overwrite newly added recommendations during slow hydration', async () => {
  const { relations: state, open } = mountEditor()
  let finishDetails!: (attributes: []) => void
  api.getCategoryAttributes.mockReturnValueOnce(
    new Promise<[]>((resolve) => {
      finishDetails = resolve
    }),
  )
  api.getProductRelations.mockResolvedValue([
    {
      related_product_id: 2,
      related_product: first,
      type: 'related',
      sort_order: 0,
    },
  ])
  const opening = open(product)
  await flushPromises()
  state.relationSearch.value = '5000033'
  await state.searchRelations()
  state.addRelation('related')
  // Actions wait for initial hydration rather than creating drafts it can erase.
  expect(state.relations.value).toEqual([])
  finishDetails([])
  await opening
  await state.searchRelations()
  state.addRelation('related')
  expect(state.relations.value.map((row) => row.related_product_id)).toEqual([
    '2',
    '3',
  ])
})

test('rejects partial SKU, self and duplicate products but allows readding a removed draft', async () => {
  const { relations: state } = await openEditor()
  api.getRelationCandidates.mockResolvedValue([product, first, second])
  state.relationSearch.value = '60000'
  await state.searchRelations()
  expect(state.relationSearchResult.value).toBeNull()
  state.relationSearch.value = 'SELF'
  await state.searchRelations()
  expect(state.relationSearchResult.value).toBeNull()
  expect(state.relationSearchMessage.value).toContain('редактируемого')
  state.relationSearch.value = first.sku
  await state.searchRelations()
  state.addRelation('related')
  state.relationSearch.value = first.sku
  await state.searchRelations()
  expect(state.relationSearchResult.value).toBeNull()
  expect(state.relationSearchMessage.value).toContain('уже добавлен')
  expect(state.addRelation('recommended')).toBe(false)
  state.removeRelation(0)
  // The API can still exclude this saved relation until the next PUT.
  api.getRelationCandidates.mockResolvedValue([])
  await state.searchRelations()
  expect(state.relationSearchResult.value?.id).toBe(2)
  state.addRelation('recommended')
  expect(state.relations.value).toHaveLength(1)
  expect(state.relations.value[0]?.type).toBe('recommended')
})
