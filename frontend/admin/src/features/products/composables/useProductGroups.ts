import { computed, ref, type Ref } from 'vue'
import { ApiError } from '../../../services/apiError'
import { sortByLabel } from '../../../utils/alphabetical'
import {
  deleteProductGroup,
  getAllProductGroups,
  saveProductGroup,
} from '../services/productGroups'
import { getProducts } from '../services/products'
import type {
  Product,
  ProductGroup,
  ProductGroupPayload,
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
) {
  const confirmError = ref('')
  const groupProducts = ref<ProductGroup['products']>([])
  const groups = ref<ProductGroup[]>([])
  const selectedGroupId = ref('')
  const groupErrors = ref<Record<string, string[]>>({})
  const groupForm = ref<ProductGroupPayload>(emptyGroup())
  const groupSearch = ref('')
  const groupDeleting = ref(false)
  const groupOptions = computed(() => [
    { value: '', label: 'Новая группа' },
    ...sortByLabel(
      groups.value.map((group) => ({
        value: String(group.id),
        label: `${group.name} · ${group.code}`,
      })),
    ),
  ])
  const currentGroup = computed(
    () =>
      groups.value.find((group) =>
        group.products.some((product) => product.id === editing.value?.id),
      ) ?? null,
  )
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
    selectedGroupId.value = group ? String(group.id) : ''
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
  function prepareGroupDraft(product: Product, groupList: ProductGroup[]) {
    const ownGroup =
      groupList.find((group) =>
        group.products.some((item) => item.id === product.id),
      ) ?? null
    if (ownGroup) {
      groupProducts.value = ownGroup.products
      fillGroup(ownGroup, product.id)
      return
    }
    const source = copiedFromProduct.value
    const compatible =
      source &&
      source.category_id === product.category_id &&
      source.brand_id === product.brand_id
    if (!source || !compatible) {
      groupProducts.value = [product]
      fillGroup(null, product.id)
      return
    }
    const sourceGroup =
      groupList.find((group) =>
        group.products.some((item) => item.id === source.id),
      ) ?? null
    if (sourceGroup) {
      groupProducts.value = [...sourceGroup.products, product]
      fillGroup(sourceGroup, product.id)
      groupForm.value.product_ids = [...groupForm.value.product_ids, product.id]
      return
    }
    groupProducts.value = [source, product]
    fillGroup(null, product.id)
    groupForm.value.product_ids = [source.id, product.id]
  }

  function reset() {
    groups.value = []
    groupProducts.value = []
    selectedGroupId.value = ''
    groupForm.value = emptyGroup()
    groupErrors.value = {}
    groupSearch.value = ''
  }

  function apply(product: Product, groupList: ProductGroup[]) {
    groups.value = groupList
    prepareGroupDraft(product, groupList)
  }

  function requestGroupDeletion() {
    confirmError.value = ''
    groupDeleting.value = true
  }

  function closeGroupDeletion() {
    confirmError.value = ''
    groupDeleting.value = false
  }

  function selectGroup(id: string) {
    if (!editing.value) return
    fillGroup(
      groups.value.find((g) => String(g.id) === id) ?? null,
      editing.value.id,
    )
  }
  async function searchGroupProducts() {
    if (!editing.value || saving.value) return
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
    if (!editing.value || saving.value) return
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
      groups.value = await getAllProductGroups()
      fillGroup(saved, editing.value.id)
      success.value = 'Группа вариантов сохранена.'
      onSaved()
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
    if (!editing.value || !selectedGroupId.value || saving.value) return
    saving.value = true
    confirmError.value = ''
    try {
      await deleteProductGroup(Number(selectedGroupId.value))
      groups.value = await getAllProductGroups()
      fillGroup(null, editing.value.id)
      groupDeleting.value = false
      success.value = 'Группа удалена.'
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
    reset,
    load: getAllProductGroups,
    apply,
    selectGroup,
    searchGroupProducts,
    saveGroup,
    requestGroupDeletion,
    closeGroupDeletion,
    ungroup,
  }
}
