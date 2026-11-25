import { apiFetch } from '../../../services/api'
import type { AuditLogFilters, AuditLogPage } from '../types/auditLog.types'

export async function getAuditLogs(
  filters: AuditLogFilters = {},
): Promise<AuditLogPage> {
  const query = new URLSearchParams({ per_page: '25' })
  Object.entries(filters).forEach(([key, value]) => {
    if (value) query.set(key, String(value))
  })
  const response = await apiFetch(`/admin/audit-logs?${query}`, {
    errorMessage: 'Не удалось загрузить журнал аудита.',
    errorMessageSource: 'fallback',
  })
  return (await response.json()) as AuditLogPage
}
