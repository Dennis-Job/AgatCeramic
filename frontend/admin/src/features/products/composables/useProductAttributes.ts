import { computed, ref } from 'vue'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { getCategoryAttributeGroups } from '../../categories/services/categories'
import {
  getCategoryAttributes,
  getProductAttributeValues,
  saveProductAttributeValues,
} from '../services/productAttributes'
import type { AttributeDraftValue, Product } from '../types/product.types'
import type { ProductEditorSession } from '../types/productEditor.types'

function inputValue(v: unknown): AttributeDraftValue {
  if (Array.isArray(v)) return v.map(String)
  if (typeof v === 'boolean') return v ? 'true' : 'false'
  return String(v ?? '')
}
function attributeLabel(attribute: Attribute): string {
  return `${attribute.name}${attribute.unit ? ` (${attribute.unit})` : ''}`
}
function displayAttributeValue(
  attribute: Attribute | undefined,
  value: unknown,
): string {
  if (attribute?.type === 'select')
    return (
      attribute.options.find((option) => option.value === String(value))
        ?.label ?? String(value ?? '')
    )
  if (typeof value === 'boolean') return value ? 'Да' : 'Нет'
  if (Array.isArray(value)) return value.map((item) => String(item)).join(', ')
  return String(value ?? '')
}
function hasValue(v: AttributeDraftValue | undefined) {
  return Array.isArray(v) ? v.length > 0 : String(v ?? '').trim() !== ''
}
function typed(
  a: Attribute,
  v: AttributeDraftValue,
): string | number | boolean | string[] {
  if (a.type === 'integer') return Number.parseInt(String(v), 10)
  if (a.type === 'decimal') return Number(v)
  if (a.type === 'boolean') return v === 'true'
  if (a.type === 'multiselect') return Array.isArray(v) ? v : []
  return String(v)
}

export function useProductAttributes(
  { editing, saving, error, success }: ProductEditorSession,
  groupRolesPending: () => boolean,
  onSaved: () => void,
) {
  const attributes = ref<Attribute[]>([])
  const attributeGroups = ref<AttributeGroup[]>([])
  const attributeValues = ref<Record<number, AttributeDraftValue>>({})
  const requiredIds = ref<number[]>([])
  const missingRequiredNames = computed(() =>
    attributes.value
      .filter(
        (attribute) =>
          requiredIds.value.includes(attribute.id) &&
          !hasValue(attributeValues.value[attribute.id]),
      )
      .map((attribute) => attribute.name),
  )
  const groupedAttributeSections = computed(() =>
    attributeGroups.value
      .map((group) => ({
        ...group,
        attributes: attributes.value.filter(
          (attribute) => attribute.attribute_group_id === group.id,
        ),
      }))
      .filter((group) => group.attributes.length),
  )
  const groupedAttributeIds = computed(
    () =>
      new Set(
        groupedAttributeSections.value.flatMap((group) =>
          group.attributes.map((attribute) => attribute.id),
        ),
      ),
  )
  const ungroupedAttributes = computed(() =>
    attributes.value.filter(
      (attribute) => !groupedAttributeIds.value.has(attribute.id),
    ),
  )
  const attributeSections = computed(() => [
    ...groupedAttributeSections.value,
    ...(ungroupedAttributes.value.length
      ? [
          {
            id: 'ungrouped',
            name: 'Без группы',
            attributes: ungroupedAttributes.value,
          },
        ]
      : []),
  ])
  function copyAttributeDrafts() {
    return Object.fromEntries(
      Object.entries(attributeValues.value).map(([id, value]) => [
        id,
        Array.isArray(value) ? [...value] : value,
      ]),
    )
  }

  function reset() {
    attributes.value = []
    attributeGroups.value = []
    attributeValues.value = {}
    requiredIds.value = []
  }

  function load(product: Product) {
    return Promise.all([
      getCategoryAttributes(product.category_id),
      getCategoryAttributeGroups(product.category_id),
      getProductAttributeValues(product.id),
    ])
  }

  function apply(
    [categoryAttrs, categoryAttributeGroups, savedAttrs]: Awaited<
      ReturnType<typeof load>
    >,
    copiedAttributes: Record<number, AttributeDraftValue> | null = null,
  ) {
    attributes.value = categoryAttrs
    attributeGroups.value = categoryAttributeGroups
    requiredIds.value = categoryAttrs
      .filter((attribute) => attribute.is_required)
      .map((attribute) => attribute.id)
    const saved = new Map(
      savedAttrs.map((value) => [value.attribute_id, value.value]),
    )
    attributeValues.value = Object.fromEntries(
      categoryAttrs.map((attribute) => [
        attribute.id,
        copiedAttributes && attribute.id in copiedAttributes
          ? copiedAttributes[attribute.id]
          : inputValue(saved.get(attribute.id)),
      ]),
    )
  }

  async function saveAttributes() {
    if (!editing.value || saving.value) return
    if (groupRolesPending()) {
      error.value =
        'Сначала сохраните роли характеристик на шаге «Варианты модели».'
      return
    }
    error.value = ''
    saving.value = true
    try {
      await saveProductAttributeValues(editing.value.id, {
        attributes: attributes.value
          .filter((a) => hasValue(attributeValues.value[a.id]))
          .map((a) => ({
            attribute_id: a.id,
            value: typed(a, attributeValues.value[a.id]),
          })),
      })
      success.value = 'Характеристики сохранены.'
      onSaved()
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить характеристики.'
    } finally {
      saving.value = false
    }
  }

  return {
    attributes,
    attributeValues,
    requiredIds,
    attributeSections,
    attributeLabel,
    displayAttributeValue,
    missingRequiredNames,
    copyAttributeDrafts,
    reset,
    load,
    apply,
    saveAttributes,
  }
}
