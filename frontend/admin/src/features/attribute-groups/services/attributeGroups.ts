import { apiFetch } from '../../../services/api'
import {
  loadAllPages,
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type {
  AttributeGroup,
  AttributeGroupPayload,
} from '../types/attributeGroup.types'
export type {
  AttributeGroup,
  AttributeGroupPayload,
} from '../types/attributeGroup.types'
export async function getAttributeGroups(
  request: PageRequest = {},
): Promise<PaginatedResponse<AttributeGroup>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/attribute-groups${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<AttributeGroup>
}
export async function getAllAttributeGroups(): Promise<AttributeGroup[]> {
  return loadAllPages(getAttributeGroups)
}
export async function saveAttributeGroup(
  id: number | null,
  payload: AttributeGroupPayload,
): Promise<AttributeGroup> {
  const response = await apiFetch(
    id === null ? '/admin/attribute-groups' : `/admin/attribute-groups/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: AttributeGroup }).data
}
export async function deleteAttributeGroup(id: number): Promise<void> {
  await apiFetch(`/admin/attribute-groups/${id}`, {
    method: 'DELETE',
  })
}
