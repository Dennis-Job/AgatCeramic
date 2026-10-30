import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  getCollections,
  saveCollection,
  deleteCollection,
} from '../services/collections'
import type { ProductCollection } from '../types/collection.types'
import type { PaginationMeta } from '../../../services/pagination'
export function useCollectionCatalog() {
  const collections = ref<ProductCollection[]>([])
  const pagination = ref<PaginationMeta | null>(null)
  const search = ref('')
  const brandId = ref('')
  const activity = ref('')
  const error = ref('')
  const loading = ref(false)
  const pending = ref(false)
  let version = 0
  let timer: ReturnType<typeof setTimeout> | undefined
  let loadedKey = ''
  const key = () =>
    JSON.stringify([search.value.trim(), brandId.value, activity.value])
  async function load(
    page = key() === loadedKey ? (pagination.value?.current_page ?? 1) : 1,
  ) {
    clearTimeout(timer)
    pending.value = false
    const token = ++version
    const queryKey = key()
    loading.value = true
    error.value = ''
    try {
      const result = await getCollections({
        page,
        search: search.value.trim(),
        brand_id: brandId.value ? Number(brandId.value) : undefined,
        is_active: activity.value === '' ? undefined : activity.value === '1',
      })
      if (version !== token) return
      collections.value = result.data
      pagination.value = result.meta
      loadedKey = queryKey
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
  watch(
    [search, brandId, activity],
    () => {
      ++version
      clearTimeout(timer)
      pending.value = true
      timer = setTimeout(() => void load(1), 350)
    },
    { flush: 'sync' },
  )
  onBeforeUnmount(() => {
    ++version
    clearTimeout(timer)
  })
  async function loadAfterDeletion() {
    await load(
      collections.value.length <= 1
        ? Math.max(1, (pagination.value?.current_page ?? 1) - 1)
        : (pagination.value?.current_page ?? 1),
    )
  }
  return {
    collections,
    pagination,
    search,
    brandId,
    activity,
    error,
    loading: computed(() => loading.value || pending.value),
    load,
    loadAfterDeletion,
    saveCollection,
    removeCollection: deleteCollection,
  }
}
