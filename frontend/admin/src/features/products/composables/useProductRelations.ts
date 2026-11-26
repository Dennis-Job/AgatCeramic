import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import { ApiError } from '../../../services/apiError'
import { sortByLabel } from '../../../utils/alphabetical'
import {
  getProductRelations,
  getRelationCandidates,
  saveProductRelations,
} from '../services/productRelations'
import type {
  Product,
  ProductRelation,
  ProductRelationDraft,
  ProductRelationType,
} from '../types/product.types'
import type { ProductEditorSession } from '../types/productEditor.types'

function toDraft(relation: ProductRelation): ProductRelationDraft {
  return {
    related_product_id: String(relation.related_product_id),
    type: relation.type,
    sort_order: String(relation.sort_order),
  }
}

export function useProductRelations(
  { editing, saving, error, success }: ProductEditorSession,
  opened: Ref<boolean>,
  photosOnly: Ref<boolean>,
  relationDetailsLoading: Readonly<Ref<boolean>>,
) {
  const candidates = ref<Product[]>([])
  const relations = ref<ProductRelationDraft[]>([])
  const relationErrors = ref<Record<number, string>>({})
  const relationSearch = ref('')
  const relationSearchLoading = ref(false)
  const relationSearchError = ref('')
  const relationLookupResult = ref<Product | null>(null)
  const relationSearchCompleted = ref(false)
  const relationActionStatus = ref('')
  const relationKnownProductIds = new Set<number>()
  let relationSearchRequest = 0
  let relationSearchTimer: ReturnType<typeof setTimeout> | null = null
  const relationTypes = sortByLabel([
    { value: 'related', label: 'Сопутствующий товар' },
    { value: 'recommended', label: 'Рекомендуемый товар' },
  ])
  const normalizeSku = (sku: string) => sku.trim().toLocaleLowerCase('ru')
  const relationSkuExclusion = computed(() => {
    const sku = normalizeSku(relationSearch.value)
    if (!sku) return ''
    if (sku === normalizeSku(editing.value?.sku ?? ''))
      return 'Это SKU редактируемого товара. Выберите другой товар.'
    if (
      candidates.value.some(
        (product) =>
          normalizeSku(product.sku) === sku &&
          relations.value.some(
            (row) => Number(row.related_product_id) === product.id,
          ),
      )
    )
      return 'Этот товар уже добавлен. Тип связи можно изменить в списке ниже.'
    return ''
  })
  const relationSearchResult = computed(() =>
    relationSearchLoading.value ||
    relationDetailsLoading.value ||
    relationSearchError.value ||
    relationSkuExclusion.value
      ? null
      : relationLookupResult.value,
  )
  const relationSearchMessage = computed(
    () =>
      relationSkuExclusion.value ||
      (relationSearchCompleted.value &&
      !relationLookupResult.value &&
      relationSearch.value.trim()
        ? 'Товар с таким SKU не найден среди доступных для добавления.'
        : ''),
  )
  function relationProduct(id: string) {
    return candidates.value.find((product) => String(product.id) === id)
  }

  function reset() {
    relations.value = []
    relationErrors.value = {}
    resetRelationSearch()
  }

  function apply(relationList: ProductRelation[]) {
    relations.value = relationList.map(toDraft)
    candidates.value = relationList.map((relation) => relation.related_product)
    candidates.value.forEach((product) =>
      relationKnownProductIds.add(product.id),
    )
  }

  function addRelation(type: ProductRelationType): boolean {
    const product = relationSearchResult.value
    if (
      product &&
      editing.value &&
      opened.value &&
      product.id !== editing.value.id &&
      !saving.value &&
      !relationDetailsLoading.value &&
      !relationSearchLoading.value &&
      !relations.value.some(
        (row) => Number(row.related_product_id) === product.id,
      )
    ) {
      relations.value.push({
        related_product_id: String(product.id),
        type,
        sort_order: String(
          Math.max(
            -1,
            ...relations.value.map((row) => Number(row.sort_order) || 0),
          ) + 1,
        ),
      })
      relationKnownProductIds.add(product.id)
      relationErrors.value = {}
      relationSearch.value = ''
      success.value = ''
      relationActionStatus.value =
        type === 'related'
          ? 'Товар добавлен в сопутствующие.'
          : 'Товар добавлен в рекомендуемые.'
      return true
    }
    return false
  }
  function removeRelation(index: number) {
    if (saving.value || relationDetailsLoading.value) return
    relations.value.splice(index, 1)
    relationErrors.value = {}
    success.value = ''
    relationActionStatus.value = 'Товар удалён из списка.'
    if (relationSearch.value.trim()) void searchRelations()
  }
  async function searchRelations() {
    if (relationSearchTimer) clearTimeout(relationSearchTimer)
    relationSearchTimer = null
    if (
      !editing.value ||
      !opened.value ||
      photosOnly.value ||
      relationDetailsLoading.value
    )
      return
    const productId = editing.value.id
    const query = relationSearch.value.trim()
    const request = ++relationSearchRequest
    relationLookupResult.value = null
    relationSearchCompleted.value = false
    relationSearchError.value = ''
    relationSearchLoading.value = false
    if (!query || relationSkuExclusion.value) return
    relationSearchLoading.value = true
    try {
      const found = await getRelationCandidates(productId, query, 20)
      if (request !== relationSearchRequest) return
      const exact =
        found.find(
          (product) => normalizeSku(product.sku) === normalizeSku(query),
        ) ??
        candidates.value.find(
          (product) =>
            relationKnownProductIds.has(product.id) &&
            normalizeSku(product.sku) === normalizeSku(query),
        )
      const available =
        exact &&
        exact.id !== productId &&
        !relations.value.some(
          (row) => Number(row.related_product_id) === exact.id,
        )
      relationLookupResult.value = available ? exact : null
      candidates.value = candidates.value.filter((product) =>
        relationKnownProductIds.has(product.id),
      )
      if (
        available &&
        !candidates.value.some((product) => product.id === exact.id)
      )
        candidates.value.push(exact)
      relationSearchCompleted.value = true
    } catch (reason) {
      if (request !== relationSearchRequest) return
      relationSearchError.value =
        reason instanceof Error ? reason.message : 'Не удалось найти товары.'
    } finally {
      if (request === relationSearchRequest) relationSearchLoading.value = false
    }
  }
  function resetRelationSearch() {
    relationSearch.value = ''
    if (relationSearchTimer) clearTimeout(relationSearchTimer)
    relationSearchTimer = null
    relationSearchRequest++
    relationSearchLoading.value = false
    relationSearchError.value = ''
    relationLookupResult.value = null
    relationSearchCompleted.value = false
    relationActionStatus.value = ''
    relationKnownProductIds.clear()
    candidates.value = []
  }
  watch([opened, () => editing.value?.id, photosOnly], resetRelationSearch, {
    flush: 'sync',
  })
  watch(
    relationSearch,
    () => {
      if (relationSearchTimer) clearTimeout(relationSearchTimer)
      relationSearchRequest++
      relationLookupResult.value = null
      relationSearchCompleted.value = false
      relationActionStatus.value = ''
      relationSearchError.value = ''
      relationSearchLoading.value = false
      if (
        !editing.value ||
        !opened.value ||
        photosOnly.value ||
        relationDetailsLoading.value
      )
        return
      if (!relationSearch.value.trim() || relationSkuExclusion.value) return
      relationSearchLoading.value = true
      relationSearchTimer = setTimeout(() => void searchRelations(), 350)
    },
    { flush: 'sync' },
  )
  async function saveRelations() {
    if (!editing.value || saving.value || relationDetailsLoading.value) return
    relationErrors.value = {}
    error.value = ''
    success.value = ''
    saving.value = true
    try {
      const saved = await saveProductRelations(editing.value.id, {
        relations: relations.value.map((r) => ({
          related_product_id: Number(r.related_product_id),
          type: r.type,
          sort_order: Number(r.sort_order),
        })),
      })
      relations.value = saved.map(toDraft)
      success.value = 'Связи товаров сохранены.'
    } catch (reason) {
      if (reason instanceof ApiError)
        Object.entries(reason.details).forEach(([key, messages]) => {
          const m = key.match(/^relations\.(\d+)/)
          if (m) relationErrors.value[Number(m[1])] = messages[0]
        })
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить рекомендации.'
    } finally {
      saving.value = false
    }
  }

  onBeforeUnmount(resetRelationSearch)

  return {
    relations,
    relationErrors,
    relationSearch,
    relationSearchLoading,
    relationDetailsLoading,
    relationSearchError,
    relationSearchResult,
    relationSearchMessage,
    relationActionStatus,
    relationProduct,
    relationTypes,
    reset,
    load: getProductRelations,
    apply,
    searchRelations,
    addRelation,
    removeRelation,
    saveRelations,
  }
}
