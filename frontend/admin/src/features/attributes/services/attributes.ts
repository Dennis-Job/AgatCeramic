import { apiFetch } from '../../../services/api'
import {
  loadAllPages,
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type {
  Attribute,
  AttributePayload,
  AttributeDisplayPatch,
} from '../types/attribute.types'
export type {
  Attribute,
  AttributeOption,
  AttributePayload,
  AttributeType,
} from '../types/attribute.types'
export async function getAttributes(
  request: PageRequest = {},
): Promise<PaginatedResponse<Attribute>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/attributes${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<Attribute>
}
export async function getAllAttributes(): Promise<Attribute[]> {
  return loadAllPages(getAttributes)
}
export async function saveAttribute(
  id: number | null,
  payload: AttributePayload,
): Promise<Attribute> {
  const body: Partial<AttributePayload> = { ...payload }
  if (!['select', 'multiselect'].includes(payload.type)) delete body.options
  const response = await apiFetch(
    id === null ? '/admin/attributes' : `/admin/attributes/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    },
  )
  return ((await response.json()) as { data: Attribute }).data
}
export async function deleteAttribute(id: number): Promise<void> {
  await apiFetch(`/admin/attributes/${id}`, {
    method: 'DELETE',
  })
}
export async function patchAttributeDisplay(
  id: number,
  payload: AttributeDisplayPatch,
): Promise<Attribute> {
  const response = await apiFetch(`/admin/attributes/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  return ((await response.json()) as { data: Attribute }).data
}
