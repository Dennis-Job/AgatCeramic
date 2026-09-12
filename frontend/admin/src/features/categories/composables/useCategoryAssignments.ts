import { computed, ref } from 'vue'
import { getAllAttributes } from '../../attributes/services/attributes'
import { getAllAttributeGroups } from '../../attribute-groups/services/attributeGroups'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import type { Category } from '../types/category.types'
import { getCategoryAttributeGroups, getCategoryAttributes, replaceCategoryAttributeGroups, replaceCategoryAttributes } from '../services/categories'

export function useCategoryAssignments() {
  const open = ref(false); const busy = ref(false); const error = ref(''); const category = ref<Category | null>(null)
  const attributes = ref<Attribute[]>([]); const groups = ref<AttributeGroup[]>([]); const selectedAttributeIds = ref<number[]>([]); const requiredAttributeIds = ref<number[]>([]); const selectedGroupIds = ref<number[]>([])
  const groupedAttributes = computed(() => groups.value.filter(group => selectedGroupIds.value.includes(group.id)).map(group => ({ ...group, attributes: attributes.value.filter(attribute => attribute.attribute_group_id === group.id) })))
  const ungroupedAttributes = computed(() => attributes.value.filter(attribute => attribute.attribute_group_id === null))
  async function show(value: Category): Promise<void> { category.value = value; error.value = ''; open.value = true; try { const [availableAttributes, availableGroups, assignedAttributes, assignedGroups] = await Promise.all([getAllAttributes(), getAllAttributeGroups(), getCategoryAttributes(value.id), getCategoryAttributeGroups(value.id)]); attributes.value = availableAttributes; groups.value = availableGroups; selectedAttributeIds.value = assignedAttributes.map(item => item.id); requiredAttributeIds.value = assignedAttributes.filter(item => item.is_required).map(item => item.id); selectedGroupIds.value = assignedGroups.map(item => item.id) } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить характеристики категории.' } }
  function setGroupIds(ids: Array<number | string>): void { const previous = selectedGroupIds.value; selectedGroupIds.value = ids.map(Number); const removed = previous.filter(id => !selectedGroupIds.value.includes(id)); if (removed.length) selectedAttributeIds.value = selectedAttributeIds.value.filter(id => !removed.includes(attributes.value.find(item => item.id === id)?.attribute_group_id ?? -1)); requiredAttributeIds.value = requiredAttributeIds.value.filter(id => selectedAttributeIds.value.includes(id)) }
  function setAttributeIds(ids: Array<number | string>): void { selectedAttributeIds.value = ids.map(Number); requiredAttributeIds.value = requiredAttributeIds.value.filter(id => selectedAttributeIds.value.includes(id)) }
  async function submit(): Promise<void> { if (!category.value) return; busy.value = true; error.value = ''; try { await replaceCategoryAttributeGroups(category.value.id, selectedGroupIds.value.map((id, sort_order) => ({ id, sort_order }))); await replaceCategoryAttributes(category.value.id, selectedAttributeIds.value.map((id, sort_order) => ({ id, sort_order, is_required: requiredAttributeIds.value.includes(id) }))); open.value = false } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить характеристики категории.' } finally { busy.value = false } }
  return { open, busy, error, category, attributes, groups, selectedAttributeIds, requiredAttributeIds, selectedGroupIds, groupedAttributes, ungroupedAttributes, show, close: () => { open.value = false }, setGroupIds, setAttributeIds, submit }
}
