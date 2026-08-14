import { apiFetch, requestCsrfCookie } from './auth'
import type { Attribute } from './attributes'

export type Category = { id: number; parent_id?: number | null; name: string; slug: string; description: string | null; is_parent: boolean; is_active: boolean; sort_order: number; children?: Category[]; created_at: string; updated_at: string }
export type CategoryPayload = Omit<Category, 'id' | 'created_at' | 'updated_at' | 'children'>

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function getCategories(): Promise<Category[]> {
  const response = await apiFetch('/admin/categories/tree')
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Category[] }).data
}

export async function saveCategory(id: number | null, payload: CategoryPayload): Promise<Category> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/categories' : `/admin/categories/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Category }).data
}

export async function deleteCategory(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/categories/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}

export async function getCategoryAttributes(id: number): Promise<Attribute[]> {
  const response = await apiFetch(`/admin/categories/${id}/attributes`)
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Attribute[] }).data
}

export async function replaceCategoryAttributes(id: number, attributes: { id: number; sort_order: number }[]): Promise<Attribute[]> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/categories/${id}/attributes`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ attributes }) })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Attribute[] }).data
}
