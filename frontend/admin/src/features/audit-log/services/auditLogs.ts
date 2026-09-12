import { apiFetch } from '../../../services/auth'
import type { AuditLogFilters, AuditLogPage } from '../types/auditLog.types'

export async function getAuditLogs(filters: AuditLogFilters = {}): Promise<AuditLogPage> {
  const query = new URLSearchParams({ per_page: '25' })
  Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, String(value)) })
  const response = await apiFetch(`/admin/audit-logs?${query}`)
  if (!response.ok) throw new Error('Не удалось загрузить журнал аудита.')
  return await response.json() as AuditLogPage
}
