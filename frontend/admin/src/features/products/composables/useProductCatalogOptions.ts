import { computed, ref } from 'vue'
import { compareAlphabetically, sortByLabel } from '../../../utils/alphabetical'
import { getAllBrands } from '../../brands/services/brands'
import type { Brand } from '../../brands/types/brand.types'
import { getCategories } from '../../categories/services/categories'
import type { Category } from '../../categories/types/category.types'

type CatalogOptions = { categoryList: Category[]; brandList: Brand[] }

function flatten(nodes: Category[]): Category[] {
  return [...nodes]
    .sort((left, right) => compareAlphabetically(left.name, right.name))
    .flatMap((item) => [item, ...flatten(item.children ?? [])])
}

export function useProductCatalogOptions() {
  const categories = ref<Category[]>([])
  const brands = ref<Brand[]>([])
  let categoriesRequest: Promise<Category[]> | null = null
  let brandsRequest: Promise<Brand[]> | null = null

  const categoryOptions = computed(() =>
    flatten(categories.value)
      .filter((item) => item.is_active)
      .map((item) => ({
        value: String(item.id),
        label: item.name,
      })),
  )
  const brandOptions = computed(() => [
    { value: '', label: 'Без бренда' },
    ...sortByLabel(
      brands.value.map((item) => ({
        value: String(item.id),
        label: item.name,
      })),
    ),
  ])

  function loadCatalogOptions(): Promise<CatalogOptions> {
    categoriesRequest ??= getCategories().catch((reason: unknown) => {
      categoriesRequest = null
      throw reason
    })
    brandsRequest ??= getAllBrands().catch((reason: unknown) => {
      brandsRequest = null
      throw reason
    })
    return Promise.all([categoriesRequest, brandsRequest]).then(
      ([categoryList, brandList]) => ({ categoryList, brandList }),
    )
  }

  function applyCatalogOptions({ categoryList, brandList }: CatalogOptions) {
    categories.value = categoryList
    brands.value = brandList
  }

  return {
    categories,
    categoryOptions,
    brandOptions,
    loadCatalogOptions,
    applyCatalogOptions,
  }
}
