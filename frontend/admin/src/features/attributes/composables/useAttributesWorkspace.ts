import { computed, ref, watch } from 'vue'
import {
  getAllAttributes,
  saveAttribute,
  deleteAttribute,
  patchAttributeDisplay,
} from '../services/attributes'
import {
  getAllAttributeGroups,
  saveAttributeGroup,
  deleteAttributeGroup,
} from '../../attribute-groups/services/attributeGroups'
import { useAttributeForm } from './useAttributeForm'
import { useAttributeGroupForm } from '../../attribute-groups/composables/useAttributeGroupForm'
import type {
  Attribute,
  AttributeDisplayField,
  AttributeGroupItem,
} from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'

export function useAttributesWorkspace() {
  const attributes = ref<Attribute[]>([])
  const groups = ref<AttributeGroup[]>([])
  const loading = ref(false)
  const loadFailed = ref(false)
  const error = ref('')
  const notice = ref('')
  const selectedGroup = ref('all')
  const groupSearch = ref('')
  const search = ref('')
  const page = ref(1)
  const pendingIds = ref(new Set<number>())
  const deletingAttribute = ref<Attribute | null>(null)
  const deletingGroup = ref<AttributeGroup | null>(null)
  const deletingBusy = ref(false)
  const groupFromAttribute = ref(false)
  let loadId = 0
  let displayRevision = 0
  const pendingFields = new Map<number, AttributeDisplayField>()
  const displayUpdates = new Map<
    number,
    Partial<Record<AttributeDisplayField, { revision: number; value: boolean }>>
  >()
  function recordDisplay(
    id: number,
    field: AttributeDisplayField,
    value: boolean,
  ): void {
    displayUpdates.set(id, {
      ...displayUpdates.get(id),
      [field]: { revision: ++displayRevision, value },
    })
  }
  const sortedGroups = computed(() =>
    [...groups.value].sort(
      (a, b) => a.sort_order - b.sort_order || a.id - b.id,
    ),
  )
  const currentGroup = computed(
    () =>
      groups.value.find((group) => String(group.id) === selectedGroup.value) ??
      null,
  )
  const groupItems = computed<AttributeGroupItem[]>(() => [
    {
      value: 'all',
      label: 'Все характеристики',
      count: attributes.value.length,
    },
    ...sortedGroups.value.map((group) => ({
      value: String(group.id),
      label: group.name,
      count: attributes.value.filter(
        (attribute) => attribute.attribute_group_id === group.id,
      ).length,
    })),
    {
      value: 'ungrouped',
      label: 'Без группы',
      count: attributes.value.filter(
        (attribute) => attribute.attribute_group_id === null,
      ).length,
    },
  ])
  const visibleGroupItems = computed(() =>
    groupItems.value.filter(
      (item) =>
        item.value === 'all' ||
        item.value === 'ungrouped' ||
        item.label
          .toLocaleLowerCase('ru')
          .includes(groupSearch.value.trim().toLocaleLowerCase('ru')),
    ),
  )
  const filteredAttributes = computed(() =>
    attributes.value
      .filter(
        (attribute) =>
          selectedGroup.value === 'all' ||
          (selectedGroup.value === 'ungrouped'
            ? attribute.attribute_group_id === null
            : attribute.attribute_group_id === currentGroup.value?.id),
      )
      .filter((attribute) =>
        `${attribute.name} ${attribute.slug}`
          .toLocaleLowerCase('ru')
          .includes(search.value.trim().toLocaleLowerCase('ru')),
      )
      .sort((a, b) => a.sort_order - b.sort_order || a.id - b.id),
  )
  const pagination = computed(() => ({
    current_page: page.value,
    last_page: Math.max(1, Math.ceil(filteredAttributes.value.length / 25)),
    per_page: 25,
    total: filteredAttributes.value.length,
  }))
  const visibleAttributes = computed(() =>
    filteredAttributes.value.slice((page.value - 1) * 25, page.value * 25),
  )
  watch(
    [selectedGroup, search],
    () => {
      page.value = 1
    },
    { flush: 'sync' },
  )
  watch(
    () => pagination.value.last_page,
    (last) => {
      page.value = Math.min(page.value, last)
    },
    { flush: 'sync' },
  )
  async function load(): Promise<void> {
    const request = ++loadId
    const revision = displayRevision
    const pendingAtStart = new Map(pendingFields)
    loading.value = true
    loadFailed.value = false
    error.value = ''
    try {
      const [items, attributeGroups] = await Promise.all([
        getAllAttributes(),
        getAllAttributeGroups(),
      ])
      if (request !== loadId) return
      // A GET started around a quick PATCH can contain the old display value.
      // Merge only locally changed fields, never overwrite fresh definitions.
      attributes.value = items.map((item) => {
        const updates = displayUpdates.get(item.id)
        const merged = { ...item }
        for (const field of [
          'is_filterable',
          'is_visible_on_product_page',
        ] as const) {
          const update = updates?.[field]
          if (
            update &&
            (update.revision > revision ||
              pendingAtStart.get(item.id) === field ||
              pendingFields.get(item.id) === field)
          )
            merged[field] = update.value
        }
        return merged
      })
      groups.value = attributeGroups
      if (!groupItems.value.some((item) => item.value === selectedGroup.value))
        selectedGroup.value = 'all'
    } catch (reason) {
      if (request === loadId) {
        loadFailed.value = true
        error.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить характеристики и группы.'
      }
    } finally {
      if (request === loadId) loading.value = false
    }
  }
  const attributeEditor = useAttributeForm(saveAttribute, load)
  const groupEditor = useAttributeGroupForm(
    async (id, payload) => {
      const saved = await saveAttributeGroup(id, payload)
      groups.value = [
        ...groups.value.filter((group) => group.id !== saved.id),
        saved,
      ]
      if (groupFromAttribute.value)
        attributeEditor.form.value.attribute_group_id = saved.id
      else selectedGroup.value = String(saved.id)
      notice.value = 'Группа сохранена.'
      return saved
    },
    async () => {},
  )
  function showAttribute(attribute: Attribute | null = null): void {
    if (attribute && pendingIds.value.has(attribute.id)) return
    attributeEditor.show(attribute)
    if (!attribute)
      attributeEditor.form.value.attribute_group_id =
        currentGroup.value?.id ?? null
  }
  function showGroup(group: AttributeGroup | null = null): void {
    groupFromAttribute.value = false
    groupEditor.show(group)
  }
  function showGroupFromAttribute(): void {
    groupFromAttribute.value = true
    groupEditor.show()
  }
  async function toggleDisplay(
    attribute: Attribute,
    field: AttributeDisplayField,
    value: boolean,
  ): Promise<void> {
    if (pendingIds.value.has(attribute.id) || attribute[field] === value) return
    const previous = attribute[field]
    attribute[field] = value
    pendingFields.set(attribute.id, field)
    recordDisplay(attribute.id, field, value)
    pendingIds.value = new Set([...pendingIds.value, attribute.id])
    error.value = ''
    notice.value = ''
    try {
      const saved = await patchAttributeDisplay(attribute.id, {
        [field]: value,
      })
      const current = attributes.value.find((item) => item.id === attribute.id)
      recordDisplay(attribute.id, field, saved[field])
      if (current) current[field] = saved[field]
      notice.value = `Настройка характеристики «${attribute.name}» сохранена.`
    } catch (reason) {
      const current = attributes.value.find((item) => item.id === attribute.id)
      recordDisplay(attribute.id, field, previous)
      if (current) current[field] = previous
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить настройку.'
    } finally {
      const pending = new Set(pendingIds.value)
      pendingFields.delete(attribute.id)
      pending.delete(attribute.id)
      pendingIds.value = pending
    }
  }
  async function confirmDelete(): Promise<void> {
    const attribute = deletingAttribute.value
    const group = deletingGroup.value
    if (
      (!attribute && !group) ||
      deletingBusy.value ||
      (attribute && pendingIds.value.has(attribute.id))
    )
      return
    deletingBusy.value = true
    error.value = ''
    try {
      if (attribute) {
        await deleteAttribute(attribute.id)
        attributes.value = attributes.value.filter(
          (item) => item.id !== attribute.id,
        )
      } else if (group) {
        await deleteAttributeGroup(group.id)
        groups.value = groups.value.filter((item) => item.id !== group.id)
        attributes.value = attributes.value.map((item) =>
          item.attribute_group_id === group.id
            ? { ...item, attribute_group_id: null }
            : item,
        )
        if (selectedGroup.value === String(group.id))
          selectedGroup.value = 'ungrouped'
      }
      deletingAttribute.value = null
      deletingGroup.value = null
      notice.value = attribute
        ? 'Характеристика удалена.'
        : 'Группа удалена. Характеристики сохранены без группы.'
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить запись.'
    } finally {
      deletingBusy.value = false
    }
  }
  return {
    attributes,
    groups,
    loading,
    loadFailed,
    error,
    notice,
    selectedGroup,
    currentGroup,
    groupSearch,
    search,
    page,
    pagination,
    visibleAttributes,
    filteredAttributes,
    groupItems,
    visibleGroupItems,
    pendingIds,
    load,
    attributeEditor,
    groupEditor,
    showAttribute,
    showGroup,
    showGroupFromAttribute,
    toggleDisplay,
    deletingAttribute,
    deletingGroup,
    deletingBusy,
    confirmDelete,
  }
}
