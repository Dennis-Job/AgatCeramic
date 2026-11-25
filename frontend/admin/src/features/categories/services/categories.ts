import { apiFetch } from '../../../services/api'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import type {
  Category,
  CategoryAttributeAssignment,
  CategoryAttributeGroupAssignment,
  CategoryPayload,
} from '../types/category.types'
export type {
  Category,
  CategoryAttributeAssignment,
  CategoryAttributeGroupAssignment,
  CategoryPayload,
} from '../types/category.types'
export async function getCategories(
  includeAssignments = false,
): Promise<Category[]> {
  const response = await apiFetch(
    includeAssignments
      ? '/admin/categories/overview'
      : '/admin/categories/tree',
  )
  return ((await response.json()) as { data: Category[] }).data
}
export async function saveCategory(
  id: number | null,
  payload: CategoryPayload,
): Promise<Category> {
  const response = await apiFetch(
    id === null ? '/admin/categories' : `/admin/categories/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: Category }).data
}
export async function deleteCategory(id: number): Promise<void> {
  await apiFetch(`/admin/categories/${id}`, {
    method: 'DELETE',
  })
}
export async function getCategoryAttributes(id: number): Promise<Attribute[]> {
  const response = await apiFetch(`/admin/categories/${id}/attributes`)
  return ((await response.json()) as { data: Attribute[] }).data
}
export async function replaceCategoryAttributes(
  id: number,
  attributes: CategoryAttributeAssignment[],
): Promise<Attribute[]> {
  const response = await apiFetch(`/admin/categories/${id}/attributes`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ attributes }),
  })
  return ((await response.json()) as { data: Attribute[] }).data
}
export async function getCategoryAttributeGroups(
  id: number,
): Promise<AttributeGroup[]> {
  const response = await apiFetch(`/admin/categories/${id}/attribute-groups`)
  return ((await response.json()) as { data: AttributeGroup[] }).data
}
export async function replaceCategoryAttributeGroups(
  id: number,
  attributeGroups: CategoryAttributeGroupAssignment[],
): Promise<AttributeGroup[]> {
  const response = await apiFetch(`/admin/categories/${id}/attribute-groups`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ attribute_groups: attributeGroups }),
  })
  return ((await response.json()) as { data: AttributeGroup[] }).data
}
