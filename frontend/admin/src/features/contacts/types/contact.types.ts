export type Contact = { id: number; type: 'callback' | 'email' | 'partner'; contact: { name: string | null; phone: string | null; email: string | null }; message: string | null; source: string; status: string; assignee?: { id: number; name: string } | null; assigned_at: string | null; completed_at: string | null; created_at: string }
export type ContactStatus = { code: string; name: string; is_terminal: boolean }
export type ContactAssignee = { id: number; name: string }
export type ContactHistory = { id: number; from_status: string; to_status: string; actor: { id: number | null; name: string } | null; occurred_at: string }
export type ContactComment = { id: number; body: string; author: { id: number | null; name: string } | null; created_at: string }
export type ContactFilters = { search?: string; type?: string; status?: string; unassigned?: boolean }
