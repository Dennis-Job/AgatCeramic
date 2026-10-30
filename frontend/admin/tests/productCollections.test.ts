/* eslint-disable vue/one-component-per-file -- Independent composable harnesses. */
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { beforeEach, expect, test, vi } from 'vitest'
import { useCollectionAssignment } from '../src/features/collections/composables/useCollectionAssignment'
import { useCollectionCatalog } from '../src/features/collections/composables/useCollectionCatalog'
import { useCollectionForm } from '../src/features/collections/composables/useCollectionForm'
import type { ProductCollection } from '../src/features/collections/types/collection.types'
const api = vi.hoisted(() => ({
  getCollections: vi.fn(),
  getCollection: vi.fn(),
  saveCollection: vi.fn(),
  deleteCollection: vi.fn(),
}))
vi.mock('../src/features/collections/services/collections', () => api)
const record = (id = 1, brand = 1): ProductCollection => ({
  id,
  brand_id: brand,
  name: `Collection ${id}`,
  slug: `collection-${id}`,
  headline: null,
  description: null,
  image_id: null,
  image: null,
  brand: null,
  is_active: true,
  products_count: 0,
  created_at: '',
  updated_at: '',
})
const response = (data = [record()], page = 1, last = 1) => ({
  data,
  meta: {
    current_page: page,
    last_page: last,
    total: data.length,
    per_page: 25,
  },
})
beforeEach(() => {
  vi.resetAllMocks()
  api.getCollections.mockResolvedValue(response())
})
function setup() {
  const brandId = ref<number | null>(1)
  const categoryAllowed = ref(true)
  const collectionId = ref<number | null>(9)
  const current = ref<ProductCollection | null>({
    ...record(9),
    is_active: false,
  })
  let assignment!: ReturnType<typeof useCollectionAssignment>
  const wrapper = mount(
    defineComponent({
      setup() {
        assignment = useCollectionAssignment(
          brandId,
          categoryAllowed,
          collectionId,
          current,
        )
        return () => h('div')
      },
    }),
  )
  return { assignment, brandId, categoryAllowed, collectionId, wrapper }
}
test('preserves inactive current assignment, allows explicit clearing and warns on incompatible changes', async () => {
  const { assignment, brandId, categoryAllowed, collectionId, wrapper } =
    setup()
  await assignment.load()
  expect(
    assignment.options.value.some(
      (o) => o.value === '9' && o.label.includes('неактивна'),
    ),
  ).toBe(true)
  brandId.value = 2
  expect(collectionId.value).toBe(9)
  expect(assignment.incompatible.value).toBe(true)
  categoryAllowed.value = false
  expect(assignment.visible.value).toBe(true)
  assignment.select('')
  expect(collectionId.value).toBe(null)
  expect(assignment.visible.value).toBe(false)
  wrapper.unmount()
})
test('fetches further pages and ignores old brand response', async () => {
  const { assignment, brandId, wrapper } = setup()
  let finish!: (v: ReturnType<typeof response>) => void
  api.getCollections.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  const old = assignment.load()
  brandId.value = 2
  api.getCollections.mockResolvedValue(response([record(22, 2)], 1, 2))
  await assignment.load()
  finish(response([record(11, 1)]))
  await old
  expect(assignment.options.value.some((o) => o.value === '11')).toBe(false)
  await assignment.loadMore()
  expect(api.getCollections).toHaveBeenLastCalledWith({
    brand_id: 2,
    is_active: true,
    search: '',
    page: 2,
  })
  wrapper.unmount()
})
test('list exposes error and retry and passes filters to paginated API', async () => {
  let catalog!: ReturnType<typeof useCollectionCatalog>
  const wrapper = mount(
    defineComponent({
      setup() {
        catalog = useCollectionCatalog()
        return () => h('div')
      },
    }),
  )
  api.getCollections.mockRejectedValueOnce(new Error('Network failed'))
  await catalog.load()
  expect(catalog.error.value).toBe('Network failed')
  catalog.brandId.value = '2'
  catalog.activity.value = '0'
  catalog.search.value = '  stone '
  await catalog.load()
  expect(catalog.error.value).toBe('')
  expect(api.getCollections).toHaveBeenLastCalledWith({
    page: 1,
    search: 'stone',
    brand_id: 2,
    is_active: false,
  })
  wrapper.unmount()
})
test('form keeps existing slug, validates brand and saves optional story fields', async () => {
  const save = vi.fn().mockResolvedValue(record())
  const reload = vi.fn().mockResolvedValue(undefined)
  const editor = useCollectionForm(save, reload)
  editor.show()
  editor.updateName('Stone')
  await editor.submit()
  expect(save).not.toHaveBeenCalled()
  expect(editor.error.value).toContain('бренд')
  editor.form.value.brand_id = 1
  await editor.submit()
  expect(save).toHaveBeenCalledWith(
    null,
    expect.objectContaining({ name: 'Stone', brand_id: 1, image_id: null }),
  )
  expect(editor.open.value).toBe(false)
  editor.show(record())
  editor.updateName('New name')
  expect(editor.form.value.slug).toBe('collection-1')
})

test('inactive or foreign collections returned by a server are never offered as new assignments', async () => {
  const { assignment, collectionId, wrapper } = setup()
  api.getCollections.mockResolvedValueOnce(
    response([{ ...record(8), is_active: false }, record(7, 2), record(6)]),
  )
  await assignment.load()
  expect(assignment.options.value.map((option) => option.value)).toEqual([
    '',
    '9',
    '6',
  ])
  assignment.select('8')
  expect(collectionId.value).toBe(9)
  assignment.select('6')
  assignment.search.value = 'another query'
  expect(assignment.options.value.some((option) => option.value === '6')).toBe(
    true,
  )
  wrapper.unmount()
})

test('a brand change invalidates the pending response during the debounce gap', async () => {
  const { assignment, brandId, wrapper } = setup()
  let finish!: (value: ReturnType<typeof response>) => void
  api.getCollections.mockReturnValueOnce(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  const pending = assignment.load()
  brandId.value = 2
  finish(response([record(50, 1)]))
  await pending
  expect(assignment.options.value.some((option) => option.value === '50')).toBe(
    false,
  )
  wrapper.unmount()
})

test('pending media blocks submit and server validation remains visible without discarding form', async () => {
  const save = vi
    .fn()
    .mockRejectedValue(new Error('Изображение должно быть публичным.'))
  const editor = useCollectionForm(save, vi.fn())
  editor.show(record())
  editor.mediaPending.value = true
  await editor.submit()
  expect(save).not.toHaveBeenCalled()
  editor.mediaPending.value = false
  await editor.submit()
  expect(editor.open.value).toBe(true)
  expect(editor.error.value).toContain('публичным')
  expect(editor.form.value.name).toBe('Collection 1')
})
