import { computed, onBeforeUnmount, ref, type Ref } from 'vue'
import { ApiError } from '../../../services/apiError'
import { sortByLabel } from '../../../utils/alphabetical'
import {
  deleteProductGroup,
  getProductGroup,
  lookupProductGroups,
  saveProductGroup,
} from '../services/productGroups'
import { getProducts } from '../services/products'
import type {
  Product,
  ProductGroup,
  ProductGroupPayload,
  ProductGroupSummary,
} from '../types/product.types'
import type { ProductEditorSession } from '../types/productEditor.types'

function emptyGroup(productIds: number[] = []): ProductGroupPayload {
  return {
    name: '',
    code: '',
    axis_attribute_ids: [],
    individual_attribute_ids: [],
    product_ids: productIds,
  }
}

export function useProductGroups(
  { editing, saving, error, success }: ProductEditorSession,
  copiedFromProduct: Readonly<Ref<Product | null>>,
  onSaved: () => void,
  onChanged: (group: ProductGroupSummary | null) => Promise<void>,
) {
  const confirmError = ref('')
  const groupProducts = ref<ProductGroup['products']>([])
  const matches = ref<ProductGroupSummary[]>([])
  const currentGroup = ref<ProductGroup | null>(null)
  const selectedGroup = ref<ProductGroup | null>(null)
  const selectedGroupId = computed(() => String(selectedGroup.value?.id ?? ''))
  const selectionLoading = ref(false)
  const selectionError = ref('')
  const lookupStatus = ref('')
  let lookupTimer: ReturnType<typeof setTimeout> | undefined
  let lookupRequest = 0
  let selectionRequest = 0
  const groupErrors = ref<Record<string, string[]>>({})
  const groupForm = ref<ProductGroupPayload>(emptyGroup())
  const groupSearch = ref('')
  const groupDeleting = ref(false)
  const groupOptions = computed(() => {
    const options = new Map(matches.value.map((group) => [group.id, group]))
    for (const group of [currentGroup.value, selectedGroup.value]) {
      if (group) options.set(group.id, group)
    }
    return [
      { value: '', label: 'Новая группа' },
      ...sortByLabel(
        Array.from(options.values(), (group) => ({
          value: String(group.id),
          label: `${group.name} · ${group.code}`,
        })),
      ),
    ]
  })
  const groupRolesPending = computed(() => {
    const group = currentGroup.value
    if (!group || selectedGroupId.value !== String(group.id)) return false
    const sameIds = (draft: number[], saved: number[]) =>
      draft.length === saved.length && draft.every((id) => saved.includes(id))
    return (
      !sameIds(
        groupForm.value.axis_attribute_ids,
        group.axes.map((a) => a.id),
      ) ||
      !sameIds(
        groupForm.value.individual_attribute_ids,
        (group.individual_attributes ?? []).map((a) => a.id),
      )
    )
  })
  function attributeScope(id: number): string {
    const group = currentGroup.value
    if (!group) return ''
    if (group.axes.some((a) => a.id === id)) return 'для выбора варианта'
    if (group.individual_attributes?.some((a) => a.id === id))
      return 'индивидуальная'
    return 'общая для группы'
  }

  function fillGroup(group: ProductGroup | null, id: number) {
    selectedGroup.value = group
    groupForm.value = group
      ? {
          name: group.name,
          code: group.code,
          axis_attribute_ids: group.axes.map((a) => a.id),
          individual_attribute_ids: (group.individual_attributes ?? []).map(
            (a) => a.id,
          ),
          product_ids: group.products.map((p) => p.id),
        }
      : emptyGroup([id])
  }

  async function loadMembership(product: Product) {
    if (!product.group) return null
    const group = await getProductGroup(product.group.id)
    return group.products.some((member) => member.id === product.id)
      ? group
      : null
  }

  function applyMembership(group: ProductGroup | null) {
    currentGroup.value = group
  }

  async function loadDraft(product: Product) {
    const source = currentGroup.value ? null : copiedFromProduct.value
    const compatibleSource =
      source &&
      source.category_id === product.category_id &&
      source.brand_id === product.brand_id
        ? source
        : null
    return {
      product,
      source: compatibleSource,
      sourceGroup: compatibleSource
        ? await loadMembership(compatibleSource)
        : null,
    }
  }

  function prepareDraft({
    product,
    source,
    sourceGroup,
  }: Awaited<ReturnType<typeof loadDraft>>) {
    const group = currentGroup.value ?? sourceGroup
    fillGroup(group, product.id)
    groupProducts.value = group?.products ?? [product]
    if (source) {
      groupProducts.value = sourceGroup
        ? [...sourceGroup.products, product]
        : [source, product]
      groupForm.value.product_ids = groupProducts.value.map(
        (member) => member.id,
      )
    }
  }

  function invalidateRequests() {
    lookupRequest++
    selectionRequest++
    clearTimeout(lookupTimer)
    selectionLoading.value = false
  }

  function searchGroups(query: string) {
    clearTimeout(lookupTimer)
    const request = ++lookupRequest
    matches.value = []
    lookupStatus.value = 'Поиск групп…'
    lookupTimer = setTimeout(
      async () => {
        try {
          const found = await lookupProductGroups(query.trim())
          if (request !== lookupRequest) return
          matches.value = found
          lookupStatus.value = found.length ? '' : 'Группы не найдены.'
        } catch {
          if (request === lookupRequest)
            lookupStatus.value =
              'Не удалось найти группы. Измените запрос или откройте список снова.'
        }
      },
      query.trim() ? 250 : 0,
    )
  }

  function reset() {
    invalidateRequests()
    matches.value = []
    lookupStatus.value = ''
    selectionError.value = ''
    currentGroup.value = null
    selectedGroup.value = null
    groupProducts.value = []
    groupForm.value = emptyGroup()
    groupErrors.value = {}
    groupSearch.value = ''
    closeGroupDeletion()
  }

  onBeforeUnmount(invalidateRequests)

  function requestGroupDeletion() {
    confirmError.value = ''
    groupDeleting.value = true
  }

  function closeGroupDeletion() {
    confirmError.value = ''
    groupDeleting.value = false
  }

  async function selectGroup(id: string) {
    const product = editing.value
    if (!product || saving.value) return
    const request = ++selectionRequest
    selectionError.value = ''
    if (id === selectedGroupId.value) {
      selectionLoading.value = false
      return
    }
    selectionLoading.value = Boolean(id)
    try {
      const group = !id
        ? null
        : currentGroup.value?.id === Number(id)
          ? currentGroup.value
          : await getProductGroup(Number(id))
      if (request !== selectionRequest) return
      fillGroup(group, product.id)
      groupProducts.value = group?.products.some(
        (member) => member.id === product.id,
      )
        ? group.products
        : [...(group?.products ?? []), product]
      groupErrors.value = {}
    } catch (reason) {
      if (request === selectionRequest)
        selectionError.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить группу.'
    } finally {
      if (request === selectionRequest) selectionLoading.value = false
    }
  }
  async function searchGroupProducts() {
    if (!editing.value || saving.value || selectionLoading.value) return
    saving.value = true
    error.value = ''
    try {
      const found = (
        await getProducts({
          search: groupSearch.value || undefined,
          category_id: editing.value.category_id,
          brand_id: editing.value.brand_id ?? undefined,
          page: 1,
          perPage: 25,
        })
      ).data
      const selected = groupProducts.value.filter((product) =>
        groupForm.value.product_ids.includes(product.id),
      )
      groupProducts.value = [
        ...selected,
        ...found.filter(
          (product) => !selected.some((item) => item.id === product.id),
        ),
      ]
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось найти товары.'
    } finally {
      saving.value = false
    }
  }
  async function saveGroup() {
    if (!editing.value || saving.value || selectionLoading.value) return
    error.value = ''
    groupErrors.value = {}
    if (!groupForm.value.axis_attribute_ids.length) {
      groupErrors.value.axis_attribute_ids = [
        'Выберите хотя бы одну характеристику.',
      ]
      return
    }
    if (groupForm.value.product_ids.length < 2) {
      groupErrors.value.product_ids = ['Добавьте минимум два товара.']
      return
    }
    saving.value = true
    try {
      const saved = await saveProductGroup(
        selectedGroupId.value ? Number(selectedGroupId.value) : null,
        groupForm.value,
      )
      if (saved.products.some((member) => member.id === editing.value?.id)) {
        currentGroup.value = saved
      } else if (currentGroup.value?.id === saved.id) {
        currentGroup.value = null
      }
      groupProducts.value = saved.products
      fillGroup(saved, editing.value.id)
      success.value = 'Группа вариантов сохранена.'
      onSaved()
      await onChanged(currentGroup.value)
    } catch (reason) {
      if (reason instanceof ApiError) groupErrors.value = reason.details
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить группу.'
    } finally {
      saving.value = false
    }
  }
  async function ungroup() {
    if (
      !editing.value ||
      !selectedGroupId.value ||
      saving.value ||
      selectionLoading.value
    )
      return
    const id = Number(selectedGroupId.value)
    saving.value = true
    confirmError.value = ''
    try {
      await deleteProductGroup(id)
      if (currentGroup.value?.id === id) {
        currentGroup.value = null
      }
      matches.value = matches.value.filter((group) => group.id !== id)
      groupProducts.value = [editing.value]
      fillGroup(null, editing.value.id)
      groupDeleting.value = false
      success.value = 'Группа удалена.'
      await onChanged(currentGroup.value)
    } catch (reason) {
      confirmError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить группу.'
    } finally {
      saving.value = false
    }
  }

  return {
    groupProducts,
    selectedGroupId,
    groupOptions,
    groupErrors,
    groupForm,
    groupSearch,
    groupDeleting,
    confirmError,
    currentGroup,
    groupRolesPending,
    attributeScope,
    searchGroups,
    lookupStatus,
    selectionLoading,
    selectionError,
    reset,
    loadMembership,
    applyMembership,
    loadDraft,
    prepareDraft,
    selectGroup,
    searchGroupProducts,
    saveGroup,
    requestGroupDeletion,
    closeGroupDeletion,
    ungroup,
  }
}
