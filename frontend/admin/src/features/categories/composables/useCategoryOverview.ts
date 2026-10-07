import { getCurrentScope, onScopeDispose, ref } from 'vue'
import type { Category, CategoryOverview } from '../types/category.types'
import type { useCategoryCatalog } from './useCategoryCatalog'

/** Tree hydration and explicit per-category refresh with stale response protection. */
export function useCategoryOverview(
  catalog: ReturnType<typeof useCategoryCatalog>,
) {
  const overview = ref<Record<number, CategoryOverview>>({})
  const versions = new Map<number, number>()
  let generation = 0
  function reset(): number {
    generation += 1
    versions.clear()
    overview.value = {}
    return generation
  }
  if (getCurrentScope()) onScopeDispose(reset)

  async function refreshOverview(id: number): Promise<void> {
    const current = generation
    const version = (versions.get(id) ?? 0) + 1
    versions.set(id, version)
    overview.value[id] = {
      attributes: [],
      groups: [],
      loading: true,
      error: '',
    }
    try {
      const [attributes, groups] = await Promise.all([
        catalog.getCategoryAttributes(id),
        catalog.getCategoryAttributeGroups(id),
      ])
      if (current !== generation || versions.get(id) !== version) return
      overview.value[id] = { attributes, groups, loading: false, error: '' }
    } catch (reason) {
      if (current !== generation || versions.get(id) !== version) return
      overview.value[id] = {
        attributes: [],
        groups: [],
        loading: false,
        error:
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить характеристики.',
      }
    }
  }

  function hydrateOverview(categories: Category[]): void {
    reset()
    for (const category of categories) {
      const complete =
        Array.isArray(category.attributes) &&
        Array.isArray(category.attribute_groups)
      overview.value[category.id] = {
        attributes: category.attributes ?? [],
        groups: category.attribute_groups ?? [],
        loading: false,
        error: complete
          ? ''
          : 'Сведения о характеристиках недоступны. Повторите загрузку.',
      }
    }
  }
  return { overview, hydrateOverview, refreshOverview, resetOverview: reset }
}
