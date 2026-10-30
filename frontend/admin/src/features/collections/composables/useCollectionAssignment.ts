import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import { getCollections } from '../services/collections'
import type { ProductCollection } from '../types/collection.types'
export function useCollectionAssignment(
  brandId: Ref<number | null>,
  categoryAllowed: Ref<boolean>,
  collectionId: Ref<number | null>,
  current: Ref<ProductCollection | null>,
) {
  const items = ref<ProductCollection[]>([])
  const selected = ref<ProductCollection | null>(null)
  const search = ref('')
  const loading = ref(false)
  const error = ref('')
  const pending = ref(false)
  const page = ref(0)
  const lastPage = ref(0)
  let version = 0
  let timer: ReturnType<typeof setTimeout> | undefined
  const currentRecord = computed(() =>
    selected.value?.id === collectionId.value
      ? selected.value
      : current.value?.id === collectionId.value
        ? current.value
        : (items.value.find((item) => item.id === collectionId.value) ?? null),
  )
  const visible = computed(
    () => categoryAllowed.value || collectionId.value !== null,
  )
  const incompatible = computed(
    () =>
      collectionId.value !== null &&
      (!categoryAllowed.value ||
        !brandId.value ||
        Boolean(
          currentRecord.value && currentRecord.value.brand_id !== brandId.value,
        )),
  )
  const options = computed(() => {
    const available = items.value.filter(
      (item) => item.is_active && item.brand_id === brandId.value,
    )
    const saved = currentRecord.value
    if (saved && !available.some((item) => item.id === saved.id))
      available.unshift(saved)
    return [
      { value: '', label: 'Без коллекции' },
      ...available.map((item) => ({
        value: String(item.id),
        label: `${item.name}${!item.is_active ? ' (неактивна)' : ''}${item.brand_id !== brandId.value ? ' (другой бренд)' : ''}`,
      })),
      ...(collectionId.value !== null &&
      !available.some((item) => item.id === collectionId.value)
        ? [
            {
              value: String(collectionId.value),
              label: `Текущая коллекция #${collectionId.value}`,
            },
          ]
        : []),
    ]
  })
  async function load(append = false) {
    clearTimeout(timer)
    pending.value = false
    const token = ++version
    if (!brandId.value || !categoryAllowed.value) {
      items.value = []
      loading.value = false
      return
    }
    const requestedBrand = brandId.value
    const requestedSearch = search.value.trim()
    const requestedPage = append ? page.value + 1 : 1
    loading.value = true
    error.value = ''
    try {
      const result = await getCollections({
        brand_id: requestedBrand,
        is_active: true,
        search: requestedSearch,
        page: requestedPage,
      })
      if (version !== token) return
      items.value = append ? [...items.value, ...result.data] : result.data
      page.value = result.meta.current_page
      lastPage.value = result.meta.last_page
    } catch (reason) {
      if (version === token)
        error.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить коллекции.'
    } finally {
      if (version === token) loading.value = false
    }
  }
  function select(value: string) {
    const id = value ? Number(value) : null
    if (
      id !== null &&
      id !== collectionId.value &&
      !items.value.some(
        (item) =>
          item.id === id && item.is_active && item.brand_id === brandId.value,
      )
    )
      return
    selected.value =
      id === null
        ? null
        : (items.value.find((item) => item.id === id) ?? currentRecord.value)
    collectionId.value = id
  }
  watch(
    [brandId, categoryAllowed, search],
    () => {
      ++version
      clearTimeout(timer)
      items.value = []
      page.value = 0
      lastPage.value = 0
      loading.value = false
      pending.value = Boolean(brandId.value && categoryAllowed.value)
      timer = setTimeout(() => void load(), 350)
    },
    { flush: 'sync' },
  )
  onBeforeUnmount(() => {
    ++version
    clearTimeout(timer)
  })
  return {
    search,
    options,
    visible,
    incompatible,
    loading: computed(() => loading.value || pending.value),
    error,
    load,
    select,
    hasMore: computed(() => page.value < lastPage.value),
    empty: computed(
      () =>
        !loading.value &&
        !pending.value &&
        !error.value &&
        Boolean(brandId.value && categoryAllowed.value) &&
        items.value.length === 0,
    ),
    loadMore: () => load(true),
  }
}
