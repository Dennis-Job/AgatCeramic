import { computed, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { deleteProduct, getProducts } from '../services/products'
import type {
  Product,
  ProductFilters,
  ProductListFilters,
  ProductSort,
  SortDirection,
} from '../types/product.types'
import type { useProductCatalogOptions } from './useProductCatalogOptions'
import { useProductFilterCounts } from './useProductFilterCounts'

function emptyFilters(): ProductListFilters {
  return {
    search: '',
    category_id: '',
    brand_id: '',
    is_active: '',
    is_on_sale: '',
  }
}

const sortLabels: Record<ProductSort, string> = {
  sku: 'SKU',
  name: 'наименованию',
  created_at: 'дате создания',
  updated_at: 'дате изменения',
}

export function useProductList(
  catalog: ReturnType<typeof useProductCatalogOptions>,
  deletingProduct: Ref<boolean>,
) {
  const list = usePaginatedCollection<Product>('Не удалось загрузить товары.')
  const { items: products, pagination, error, loading } = list
  const filters = ref(emptyFilters())
  const sort = ref<ProductSort>('created_at')
  const direction = ref<SortDirection>('desc')
  const filterResultStatus = ref('')
  const productCountUnavailable = ref(false)
  const deleting = ref<Product | null>(null)
  const confirmError = ref('')
  let listLoadVersion = 0

  function filterPayload() {
    return {
      search: filters.value.search.trim() || undefined,
      category_id: filters.value.category_id
        ? Number(filters.value.category_id)
        : undefined,
      brand_id: filters.value.brand_id
        ? Number(filters.value.brand_id)
        : undefined,
      is_active:
        filters.value.is_active === ''
          ? undefined
          : filters.value.is_active === '1',
      is_on_sale:
        filters.value.is_on_sale === ''
          ? undefined
          : filters.value.is_on_sale === '1',
    }
  }

  const {
    counts: filterCounts,
    loading: filterCountsLoading,
    error: filterCountsError,
    refresh: refreshFilterCounts,
  } = useProductFilterCounts(filterPayload)

  const sortStatus = computed(
    () =>
      `Сортировка по ${sortLabels[sort.value]}, ${direction.value === 'asc' ? 'по возрастанию' : 'по убыванию'}.`,
  )
  const activityOptions = computed(() => [
    { value: '', label: 'Все' },
    { value: '1', label: 'Активные', count: filterCounts.value.active },
    { value: '0', label: 'Скрытые', count: filterCounts.value.hidden },
  ])
  const saleOptions = computed(() => [
    { value: '', label: 'Все' },
    { value: '1', label: 'Распродажа', count: filterCounts.value.sale },
    { value: '0', label: 'Не распродажа', count: filterCounts.value.regular },
  ])
  const filterCategoryOptions = computed(() => [
    { value: '', label: 'Все категории' },
    ...catalog.categoryOptions.value,
  ])
  const filterBrandOptions = computed(() => [
    { value: '', label: 'Все бренды' },
    ...catalog.brandOptions.value.slice(1),
  ])
  const hasActiveFilters = computed(() =>
    Object.values(filters.value).some(Boolean),
  )
  const productCountLabel = computed(() => {
    if (loading.value) return 'Обновляем количество товаров…'
    if (productCountUnavailable.value) return 'Количество товаров недоступно'
    const total = pagination.value?.total
    if (total === undefined) return 'Обновляем количество товаров…'
    return hasActiveFilters.value
      ? `Найдено товаров: ${total}`
      : `Всего товаров в каталоге: ${total}`
  })

  async function fetchPage(page: number) {
    const query: ProductFilters = {
      ...filterPayload(),
      sort: sort.value,
      direction: direction.value,
      page,
      perPage: 50,
    }
    const [productPage, catalogOptions] = await Promise.all([
      getProducts(query),
      catalog.loadCatalogOptions(),
    ])
    return { ...productPage, ...catalogOptions }
  }

  async function load(
    page = pagination.value?.current_page ?? 1,
  ): Promise<boolean> {
    ++listLoadVersion
    filterResultStatus.value = ''
    productCountUnavailable.value = false
    const response = await list.load(page, fetchPage)
    if (!response) {
      if (error.value) productCountUnavailable.value = true
      return false
    }
    catalog.applyCatalogOptions(response)
    const from = response.meta.from ?? 0
    const to = response.meta.to ?? 0
    filterResultStatus.value =
      response.meta.total === 0
        ? 'Товары не найдены.'
        : `${hasActiveFilters.value ? 'Найдено товаров' : 'Всего товаров в каталоге'}: ${response.meta.total}. Показано ${from}–${to}.`
    return true
  }

  async function setSort(field: ProductSort, order: SortDirection) {
    if (loading.value) return
    if (sort.value === field && direction.value === order) return
    const previousSort = sort.value
    const previousDirection = direction.value
    sort.value = field
    direction.value = order
    const requestVersion = listLoadVersion + 1
    // A newer filter request already uses the selected sort; a stale response
    // must not roll the controls back behind the displayed results.
    if (!(await load(1)) && requestVersion === listLoadVersion) {
      sort.value = previousSort
      direction.value = previousDirection
    }
  }

  function changeSort(field: ProductSort) {
    const order =
      sort.value === field
        ? direction.value === 'asc'
          ? 'desc'
          : 'asc'
        : field === 'created_at' || field === 'updated_at'
          ? 'desc'
          : 'asc'
    return setSort(field, order)
  }

  function ariaSort(field: ProductSort): 'none' | 'ascending' | 'descending' {
    return sort.value === field
      ? direction.value === 'asc'
        ? 'ascending'
        : 'descending'
      : 'none'
  }

  function resetFilters() {
    filters.value = emptyFilters()
  }

  async function refreshAfterMutation(): Promise<void> {
    await load()
    void refreshFilterCounts()
  }

  function updateThumbnail(
    productId: number,
    primaryImage: Product['primary_image'],
  ): void {
    products.value = products.value.map((product) =>
      product.id === productId
        ? { ...product, primary_image: primaryImage }
        : product,
    )
  }

  function requestProductDeletion(product: Product) {
    confirmError.value = ''
    deleting.value = product
  }

  function closeProductDeletion() {
    confirmError.value = ''
    deleting.value = null
  }

  async function removeProduct() {
    if (!deleting.value || deletingProduct.value) return
    deletingProduct.value = true
    confirmError.value = ''
    try {
      await deleteProduct(deleting.value.id)
      deleting.value = null
      await refreshAfterMutation()
    } catch (reason) {
      confirmError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить товар.'
    } finally {
      deletingProduct.value = false
    }
  }

  let filterTimer: ReturnType<typeof setTimeout> | null = null
  let previousFilterSearch = filters.value.search
  watch(
    filters,
    (next) => {
      if (filterTimer) clearTimeout(filterTimer)
      const delay = next.search !== previousFilterSearch ? 350 : 250
      previousFilterSearch = next.search
      filterTimer = setTimeout(() => {
        filterTimer = null
        void load(1)
      }, delay)
    },
    { deep: true },
  )
  onBeforeUnmount(() => {
    if (filterTimer) clearTimeout(filterTimer)
  })
  onMounted(load)

  return {
    products,
    pagination,
    error,
    loading,
    filterResultStatus,
    filterCountsLoading,
    filterCountsError,
    productCountUnavailable,
    filters,
    sort,
    direction,
    sortStatus,
    activityOptions,
    saleOptions,
    filterCategoryOptions,
    filterBrandOptions,
    hasActiveFilters,
    productCountLabel,
    ariaSort,
    load,
    resetFilters,
    changeSort,
    setSort,
    refreshAfterMutation,
    updateThumbnail,
    deleting,
    deletingProduct,
    confirmError,
    requestProductDeletion,
    closeProductDeletion,
    removeProduct,
  }
}
