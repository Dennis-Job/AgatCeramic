import { effectScope } from 'vue'
import { expect, test, vi } from 'vitest'
import { useCategoriesWorkspace } from '../src/features/categories/composables/useCategoriesWorkspace'
import { getCategories } from '../src/features/categories/services/categories'
import type { Category } from '../src/features/categories/types/category.types'
import {
  flattenCategoryTree,
  getCategoryDescendantIds,
} from '../src/features/categories/utils/categoryTree'
import { useProductCatalogOptions } from '../src/features/products/composables/useProductCatalogOptions'
import { useProductImport } from '../src/features/products/composables/useProductImport'

vi.mock('../src/features/categories/services/categories', { spy: true })

function category(
  id: number,
  name: string,
  overrides: Partial<Category> = {},
): Category {
  return {
    id,
    name,
    slug: `category-${id}`,
    description: null,
    image_id: null,
    image: null,
    documents: [],
    sku_prefix: null,
    uses_collections: false,
    is_parent: false,
    is_active: true,
    sort_order: id,
    created_at: '2026-11-26T08:00:00Z',
    updated_at: '2026-11-26T08:00:00Z',
    ...overrides,
  }
}

const grandchild = category(5, 'Экраны', { parent_id: 3, is_parent: true })
const hidden = category(3, 'Зеркала', {
  parent_id: 1,
  is_active: false,
  is_parent: true,
  children: [grandchild],
})
const leaf = category(4, 'Душевые', { parent_id: 1 })
const branch = category(1, 'Сантехника', {
  is_parent: true,
  children: [hidden, leaf],
})
const other = category(2, 'Аксессуары', {
  is_parent: true,
  is_active: false,
  children: [],
})
const tree = [branch, other]

test('handles an empty tree and a leaf without children', () => {
  expect(flattenCategoryTree([])).toEqual([])
  expect(getCategoryDescendantIds(leaf)).toEqual([])
  expect(getCategoryDescendantIds(other)).toEqual([])
})

test('flattens in tree order and returns all descendants without their root', () => {
  expect(flattenCategoryTree(tree)).toEqual([
    branch,
    hidden,
    grandchild,
    leaf,
    other,
  ])
  expect(getCategoryDescendantIds(branch)).toEqual([3, 5, 4])
})

test('sorts siblings at each level without mutating input', () => {
  const roots = structuredClone(tree)
  Object.freeze(roots)
  Object.freeze(roots[0]!.children)
  const flattened = flattenCategoryTree(roots, (left, right) =>
    left.name.localeCompare(right.name, 'ru'),
  )

  expect(flattened.map(({ id }) => id)).toEqual([2, 1, 4, 3, 5])
  expect(roots).toEqual(tree)
})

test('product editing hides inactive categories but keeps their active descendants', () => {
  const editor = useProductCatalogOptions()
  editor.applyCatalogOptions({ categoryList: tree, brandList: [] })

  expect(editor.categoryOptions.value).toEqual([
    { value: '1', label: 'Сантехника' },
    { value: '4', label: 'Душевые' },
    { value: '5', label: 'Экраны' },
  ])
})

test('product import includes inactive categories with their existing label', async () => {
  vi.mocked(getCategories).mockResolvedValueOnce(tree)
  const scope = effectScope()
  const importer = scope.run(() => useProductImport(vi.fn()))!
  try {
    await importer.loadCategories()

    expect(importer.categoryOptions.value).toEqual([
      { value: '2', label: 'Аксессуары (скрыта)' },
      { value: '1', label: 'Сантехника' },
      { value: '4', label: 'Душевые' },
      { value: '3', label: 'Зеркала (скрыта)' },
      { value: '5', label: 'Экраны' },
    ])
  } finally {
    scope.stop()
  }
})

test('parent selection retains tree order and excludes the edited category and all its descendants', async () => {
  vi.mocked(getCategories).mockResolvedValueOnce(tree)
  const workspace = useCategoriesWorkspace()
  await workspace.load()

  expect(getCategories).toHaveBeenLastCalledWith(true)
  expect(workspace.parentOptionsFor(null)).toEqual([
    { value: '', label: 'Без родителя' },
    { value: '1', label: 'Сантехника' },
    { value: '3', label: 'Зеркала' },
    { value: '5', label: 'Экраны' },
    { value: '2', label: 'Аксессуары' },
  ])
  expect(workspace.parentOptionsFor(branch)).toEqual([
    { value: '', label: 'Без родителя' },
    { value: '2', label: 'Аксессуары' },
  ])
})
