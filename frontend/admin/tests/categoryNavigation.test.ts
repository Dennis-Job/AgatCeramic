import { expect, test } from 'vitest'
import { ref } from 'vue'
import { useCategoryNavigation } from '../src/features/categories/composables/useCategoryNavigation'
import type {
  Category,
  CategoryOverview,
} from '../src/features/categories/types/category.types'
const category = (
  id: number,
  name: string,
  parent_id: number | null,
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
  is_active: true,
  sort_order: id,
  children: [],
  created_at: '',
  updated_at: '',
})
test('sidebar search retains ancestor context and filters the detail pane to one category', () => {
  const categories = ref([
    category(1, 'Плитка', null),
    category(2, 'Для пола', 1),
    category(3, 'Сантехника', null),
  ])
  const overview = ref<Record<number, CategoryOverview>>({
    1: { attributes: [], groups: [], loading: false, error: '' },
  })
  const navigation = useCategoryNavigation(categories, overview)
  navigation.search.value = 'пола'
  expect(navigation.visibleItems.value.map((item) => item.category.id)).toEqual(
    [1, 2],
  )
  expect(navigation.visibleItems.value.map((item) => item.depth)).toEqual([
    0, 1,
  ])
  expect(navigation.items.value[1]!.path).toBe('Плитка / Для пола')
  expect(navigation.items.value[0]!.count).toBe(0)
  expect(navigation.items.value[1]!.count).toBeNull()
  navigation.selectedCategoryId.value = '2'
  expect(navigation.selectedCategory.value?.name).toBe('Для пола')
  categories.value = categories.value.filter((item) => item.id !== 2)
  expect(navigation.selectedCategoryId.value).toBe('')
})
