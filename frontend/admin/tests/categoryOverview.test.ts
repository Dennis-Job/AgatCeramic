import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, test, vi } from 'vitest'
import { effectScope, ref } from 'vue'
import CategoriesList from '../src/features/categories/components/CategoriesList.vue'
import { useCategoriesWorkspace } from '../src/features/categories/composables/useCategoriesWorkspace'
import type { Category } from '../src/features/categories/types/category.types'
import type { Attribute } from '../src/features/attributes/types/attribute.types'

const api = vi.hoisted(() => ({
  getCategories: vi.fn(),
  getCategoryAttributes: vi.fn(),
  getCategoryAttributeGroups: vi.fn(),
}))
vi.mock('../src/features/categories/composables/useCategoryCatalog', () => ({
  useCategoryCatalog: () => api,
}))
const timestamp = '2026-10-07T10:00:00Z'
const category = (
  id: number,
  name: string,
  parent_id: number | null = null,
): Category => ({
  id,
  name,
  parent_id,
  slug: `category-${id}`,
  description: null,
  image_id: null,
  image: null,
  documents: [],
  sku_prefix: String(id),
  is_parent: parent_id === null,
  is_active: id !== 3,
  sort_order: id,
  children: [],
  created_at: timestamp,
  updated_at: timestamp,
})
const attribute: Attribute = {
  id: 1,
  name: 'Толщина',
  slug: 'thickness',
  attribute_group_id: 5,
  type: 'decimal',
  unit: 'мм',
  is_required: true,
  is_filterable: true,
  is_visible_on_product_page: true,
  sort_order: 0,
  options: [],
  created_at: timestamp,
  updated_at: timestamp,
}
const groups = [
  {
    id: 5,
    name: 'Размеры',
    slug: 'dimensions',
    description: null,
    sort_order: 0,
    created_at: timestamp,
    updated_at: timestamp,
  },
]

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: Error) => void
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise
    reject = rejectPromise
  })
  return { promise, resolve, reject }
}

describe('category details request lifecycle', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    api.getCategoryAttributeGroups.mockResolvedValue(groups)
  })

  test('keeps current loading when an earlier category finishes first', async () => {
    const old = deferred<Attribute[]>()
    const current = deferred<Attribute[]>()
    api.getCategoryAttributes
      .mockReturnValueOnce(old.promise)
      .mockReturnValueOnce(current.promise)
    const workspace = useCategoriesWorkspace()
    const first = workspace.showDetails(category(1, 'Плитка'))
    const second = workspace.showDetails(category(2, 'Для пола'))
    old.resolve([attribute])
    await first
    expect(workspace.detailsLoading.value).toBe(true)
    expect(workspace.detailsAttributes.value).toEqual([])
    expect(workspace.detailsGroups.value).toEqual([])
    current.resolve([{ ...attribute, name: 'Цвет' }])
    await second
    expect(workspace.detailsLoading.value).toBe(false)
    expect(workspace.detailsAttributes.value[0]?.name).toBe('Цвет')
    expect(workspace.detailsGroups.value).toEqual(groups)
  })

  test.each(['success', 'error'] as const)(
    'ignores late %s from another category after the current response',
    async (outcome) => {
      const old = deferred<Attribute[]>()
      api.getCategoryAttributes
        .mockReturnValueOnce(old.promise)
        .mockResolvedValueOnce([{ ...attribute, name: 'Цвет' }])
      const workspace = useCategoriesWorkspace()
      const first = workspace.showDetails(category(1, 'Плитка'))
      await workspace.showDetails(category(2, 'Для пола'))
      if (outcome === 'success') old.resolve([attribute])
      else old.reject(new Error('Устаревшая ошибка'))
      await first
      expect(workspace.details.value?.id).toBe(2)
      expect(workspace.detailsAttributes.value[0]?.name).toBe('Цвет')
      expect(workspace.detailsGroups.value).toEqual(groups)
      expect(workspace.error.value).toBe('')
      expect(workspace.detailsLoading.value).toBe(false)
    },
  )

  test.each(['success', 'error'] as const)(
    'ignores late %s after closing and reopening the same category',
    async (outcome) => {
      const old = deferred<Attribute[]>()
      api.getCategoryAttributes
        .mockReturnValueOnce(old.promise)
        .mockResolvedValueOnce([{ ...attribute, name: 'Цвет' }])
      const workspace = useCategoriesWorkspace()
      const selected = category(1, 'Плитка')
      const first = workspace.showDetails(selected)
      workspace.closeDetails()
      await workspace.showDetails(selected)
      if (outcome === 'success') old.resolve([attribute])
      else old.reject(new Error('Устаревшая ошибка'))
      await first
      expect(workspace.details.value?.id).toBe(1)
      expect(workspace.detailsAttributes.value[0]?.name).toBe('Цвет')
      expect(workspace.detailsGroups.value).toEqual(groups)
      expect(workspace.error.value).toBe('')
      expect(workspace.detailsLoading.value).toBe(false)
    },
  )

  test.each(['success', 'error'] as const)(
    'closing invalidates a pending %s and clears detail state',
    async (outcome) => {
      const pending = deferred<Attribute[]>()
      api.getCategoryAttributes.mockReturnValueOnce(pending.promise)
      const workspace = useCategoriesWorkspace()
      const request = workspace.showDetails(category(1, 'Плитка'))
      workspace.closeDetails()
      expect(workspace.details.value).toBeNull()
      expect(workspace.detailsLoading.value).toBe(false)
      if (outcome === 'success') pending.resolve([attribute])
      else pending.reject(new Error('Ошибка закрытого диалога'))
      await request
      expect(workspace.detailsAttributes.value).toEqual([])
      expect(workspace.detailsGroups.value).toEqual([])
      expect(workspace.error.value).toBe('')
    },
  )

  test('applies attributes and groups together only after both responses', async () => {
    const pendingGroups = deferred<typeof groups>()
    api.getCategoryAttributes.mockResolvedValue([attribute])
    api.getCategoryAttributeGroups.mockReturnValueOnce(pendingGroups.promise)
    const workspace = useCategoriesWorkspace()
    const request = workspace.showDetails(category(1, 'Плитка'))
    await flushPromises()
    expect(workspace.detailsLoading.value).toBe(true)
    expect(workspace.detailsAttributes.value).toEqual([])
    expect(workspace.detailsGroups.value).toEqual([])
    pendingGroups.resolve(groups)
    await request
    expect(workspace.detailsAttributes.value).toEqual([attribute])
    expect(workspace.detailsGroups.value).toEqual(groups)
    expect(workspace.detailsLoading.value).toBe(false)
  })

  test('reports a current request failure and stops loading', async () => {
    api.getCategoryAttributes.mockRejectedValue(new Error('Нет доступа'))
    const workspace = useCategoriesWorkspace()
    await workspace.showDetails(category(1, 'Плитка'))
    expect(workspace.details.value).toBeNull()
    expect(workspace.error.value).toBe('Нет доступа')
    expect(workspace.detailsLoading.value).toBe(false)
  })

  test.each(['success', 'error'] as const)(
    'scope disposal ignores a pending %s',
    async (outcome) => {
      const pending = deferred<Attribute[]>()
      api.getCategoryAttributes.mockReturnValueOnce(pending.promise)
      const scope = effectScope()
      const workspace = scope.run(() => useCategoriesWorkspace())!
      const request = workspace.showDetails(category(1, 'Плитка'))
      scope.stop()
      if (outcome === 'success') pending.resolve([attribute])
      else pending.reject(new Error('Ошибка после размонтирования'))
      await request
      expect(workspace.detailsAttributes.value).toEqual([])
      expect(workspace.detailsGroups.value).toEqual([])
      expect(workspace.error.value).toBe('')
    },
  )
})

describe('category overview', () => {
  beforeEach(() => vi.resetAllMocks())
  test('hydrates all assignments from one tree request without per-category requests', async () => {
    const root = {
      ...category(1, 'Плитка'),
      attributes: [attribute],
      attribute_groups: groups,
    }
    root.children = [
      {
        ...category(2, 'Для пола', 1),
        attributes: [],
        attribute_groups: groups,
      },
    ]
    api.getCategories.mockResolvedValue([root])
    const workspace = useCategoriesWorkspace()
    await workspace.load()
    expect(workspace.overview.value[1]?.attributes[0]?.name).toBe('Толщина')
    expect(workspace.overview.value[2]?.attributes).toEqual([])
    expect(api.getCategories).toHaveBeenCalledWith(true)
    expect(api.getCategoryAttributes).not.toHaveBeenCalled()
    expect(api.getCategoryAttributeGroups).not.toHaveBeenCalled()
  })

  test('shows failed refresh separately from empty assignments and allows retry', async () => {
    api.getCategories.mockResolvedValue([
      { ...category(1, 'Плитка'), attributes: [], attribute_groups: [] },
    ])
    api.getCategoryAttributes.mockRejectedValue(
      new Error('Нет доступа к характеристикам'),
    )
    api.getCategoryAttributeGroups.mockResolvedValue(groups)
    const workspace = useCategoriesWorkspace()
    await workspace.load()
    await workspace.refreshOverview(1)
    expect(workspace.overview.value[1]?.error).toBe(
      'Нет доступа к характеристикам',
    )
    api.getCategoryAttributes.mockResolvedValue([attribute])
    await workspace.refreshOverview(1)
    expect(workspace.overview.value[1]?.error).toBe('')
    expect(workspace.overview.value[1]?.attributes[0]?.name).toBe('Толщина')
  })

  test('does not let a late assignment response replace a newer refresh', async () => {
    api.getCategories.mockResolvedValue([
      { ...category(1, 'Плитка'), attributes: [], attribute_groups: [] },
    ])
    let release!: (value: Attribute[]) => void
    api.getCategoryAttributes
      .mockImplementationOnce(
        () =>
          new Promise<Attribute[]>((resolve) => {
            release = resolve
          }),
      )
      .mockResolvedValue([{ ...attribute, name: 'Цвет' }])
    api.getCategoryAttributeGroups.mockResolvedValue(groups)
    const workspace = useCategoriesWorkspace()
    await workspace.load()
    const pending = workspace.refreshOverview(1)
    await flushPromises()
    await workspace.refreshOverview(1)
    release([attribute])
    await pending
    expect(workspace.overview.value[1]?.attributes[0]?.name).toBe('Цвет')
  })

  test('shows assigned flags and only the chosen category in the detail pane', async () => {
    const root = category(1, 'Плитка')
    root.children = [category(2, 'Для пола', 1), category(3, 'Для пола', 1)]
    const all = [root, ...root.children]
    const wrapper = mount(CategoriesList, {
      props: {
        categories: all,
        loading: false,
        canManage: false,
        categoryName: (id: number | null | undefined) =>
          all.find((item) => item.id === id)?.name ?? null,
        overview: {
          1: { attributes: [attribute], groups, loading: false, error: '' },
          2: {
            attributes: [
              { ...attribute, is_required: false, is_filterable: false },
            ],
            groups,
            loading: false,
            error: '',
          },
          3: { attributes: [], groups, loading: false, error: '' },
        },
        selectedCategoryId: '',
        'onUpdate:selectedCategoryId': (value: string) => {
          selection.value = value
          void wrapper.setProps({ selectedCategoryId: value })
        },
      },
    })
    const selection = ref('')
    const rootRow = wrapper.get('#category-1')
    expect(rootRow.text()).toContain('Обязательная')
    expect(rootRow.text()).toContain('В фильтрах')
    expect(rootRow.text()).toContain('Размеры')
    const childRow = wrapper.get('#category-2')
    expect(childRow.text()).toContain('Толщина')
    expect(childRow.text()).not.toContain('Обязательная')
    expect(childRow.text()).not.toContain('В фильтрах')
    expect(wrapper.get('#category-3').text()).toContain('Скрыта')
    expect(wrapper.get('#category-3').text()).toContain(
      'Нет назначенных характеристик',
    )
    await wrapper.setProps({ selectedCategoryId: '2' })
    expect(wrapper.find('#category-1').exists()).toBe(false)
    expect(wrapper.find('#category-2').exists()).toBe(true)
    expect(wrapper.find('#category-3').exists()).toBe(false)
    expect(
      wrapper
        .find('button[aria-label="Редактировать категорию Плитка"]')
        .exists(),
    ).toBe(false)
    await wrapper.setProps({ selectedCategoryId: '1' })
    expect(wrapper.findAll('article')).toHaveLength(1)
    await wrapper.setProps({ selectedCategoryId: '' })
    expect(wrapper.findAll('article')).toHaveLength(3)
    wrapper.unmount()
  })
})
