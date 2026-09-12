export type AuditActor = { id: number; name: string } | null
export type AuditEntity = { type: string; id: number | null; name?: string; email?: string } | null
export type AuditDetail = { label: string; value: string }
export type AuditLog = { id: number; action: string; actor: AuditActor; entity: AuditEntity; metadata: Record<string, unknown> | null; details: AuditDetail[]; occurred_at: string }
export type AuditLogFilters = { search?: string; action?: string; date_from?: string; date_to?: string; page?: number }
export type AuditLogPage = { data: AuditLog[]; meta: { current_page: number; last_page: number; total: number } }
