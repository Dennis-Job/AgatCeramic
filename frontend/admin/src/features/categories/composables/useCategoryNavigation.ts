import { computed, ref, watch, type Ref } from 'vue'
import type {
  Category,
  CategoryNavigationItem,
  CategoryOverview,
} from '../types/category.types'

export function useCategoryNavigation(
  categories: Ref<Category[]>,
  overview: Ref<Record<number, CategoryOverview>>,
  state?: { loading: Ref<boolean>; error: Ref<string> },
) {
  const selectedCategoryId = ref('')
  const search = ref('')
  const items = computed<CategoryNavigationItem[]>(() => {
    const byId = new Map(
      categories.value.map((category) => [category.id, category]),
    )
    return categories.value.map((category) => {
      const ancestors: Category[] = []
      const seen = new Set([category.id])
      let parentId = category.parent_id
      while (parentId != null && byId.has(parentId) && !seen.has(parentId)) {
        seen.add(parentId)
        const parent = byId.get(parentId)!
        ancestors.unshift(parent)
        parentId = parent.parent_id
      }
      const details = overview.value[category.id]
      return {
        category,
        depth: ancestors.length,
        ancestorIds: ancestors.map((parent) => parent.id),
        path: [...ancestors.map((parent) => parent.name), category.name].join(
          ' / ',
        ),
        count:
          details && !details.loading && !details.error
            ? details.attributes.length
            : null,
      }
    })
  })
  const visibleItems = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('ru')
    if (!query) return items.value
    const matched = items.value.filter((item) =>
      `${item.category.name} ${item.category.slug}`
        .toLocaleLowerCase('ru')
        .includes(query),
    )
    const included = new Set(
      matched.flatMap((item) => [item.category.id, ...item.ancestorIds]),
    )
    return items.value.filter((item) => included.has(item.category.id))
  })
  const selectedCategory = computed(
    () =>
      categories.value.find(
        (category) => String(category.id) === selectedCategoryId.value,
      ) ?? null,
  )
  const categoryOptions = computed(() => [
    { label: 'Все категории', value: '' },
    ...items.value.map((item) => ({
      label: item.path,
      value: String(item.category.id),
    })),
  ])
  watch(
    [categories, () => state?.loading.value, () => state?.error.value],
    ([value]) => {
      if (state?.loading.value || state?.error.value) return
      if (
        selectedCategoryId.value &&
        !value.some(
          (category) => String(category.id) === selectedCategoryId.value,
        )
      )
        selectedCategoryId.value = ''
    },
    { flush: 'sync' },
  )
  return {
    selectedCategoryId,
    selectedCategory,
    search,
    items,
    visibleItems,
    categoryOptions,
  }
}
