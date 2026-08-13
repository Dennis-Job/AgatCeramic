import { apiFetch } from './auth'

export type AuditActor = { id: number; name: string } | null
export type AuditEntity = { type: string; id: number | null; name?: string; email?: string } | null
export type AuditDetail = { label: string; value: string }
export type AuditLog = {
  id: number
  action: string
  actor: AuditActor
  entity: AuditEntity
  metadata: Record<string, unknown> | null
  details: AuditDetail[]
  occurred_at: string
}
export type AuditLogFilters = { search?: string; action?: string; date_from?: string; date_to?: string; page?: number }
export type AuditLogPage = { data: AuditLog[]; meta: { current_page: number; last_page: number; total: number } }

export async function getAuditLogs(filters: AuditLogFilters = {}): Promise<AuditLogPage> {
  const query = new URLSearchParams({ per_page: '25' })
  Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, String(value)) })
  const response = await apiFetch(`/admin/audit-logs?${query}`)

  if (!response.ok) throw new Error('Не удалось загрузить журнал аудита.')

  return await response.json() as AuditLogPage
}
