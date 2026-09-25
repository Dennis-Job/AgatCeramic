import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import type {
  ComplianceApproval,
  LegalDocument,
  SiteSettings,
} from '../types/settings.types'

async function decode<T>(response: Response): Promise<T> {
  const body = (await response.json().catch(() => ({}))) as {
    data?: T
    error?: { message?: string; details?: Record<string, string[]> }
  }
  if (!response.ok) {
    throw new Error(
      Object.values(body.error?.details ?? {}).flat()[0] ??
        body.error?.message ??
        'Не удалось выполнить запрос.',
    )
  }
  return body.data as T
}

async function write<T>(
  path: string,
  payload?: object,
  method = 'POST',
): Promise<T> {
  await requestCsrfCookie()
  return decode<T>(
    await apiFetch(path, {
      method,
      ...(payload
        ? {
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          }
        : {}),
    }),
  )
}

export async function getSettings(): Promise<SiteSettings> {
  return decode<SiteSettings>(await apiFetch('/admin/site-settings'))
}

export async function saveSettings(
  payload: Omit<SiteSettings, 'updated_at'>,
): Promise<SiteSettings> {
  return write('/admin/site-settings', payload, 'PATCH')
}

export async function getLegalDocuments(): Promise<LegalDocument[]> {
  return decode<LegalDocument[]>(await apiFetch('/admin/legal-documents'))
}

export async function createLegalDocument(
  payload: Pick<LegalDocument, 'type' | 'version' | 'body'>,
): Promise<LegalDocument> {
  return write('/admin/legal-documents', payload)
}

export async function publishLegalDocument(id: number): Promise<LegalDocument> {
  return write(`/admin/legal-documents/${id}/publish`)
}

export async function getComplianceApprovals(): Promise<ComplianceApproval[]> {
  return decode<ComplianceApproval[]>(
    await apiFetch('/admin/compliance-approvals'),
  )
}

export async function createComplianceApproval(
  payload: Pick<
    ComplianceApproval,
    'role' | 'reviewer_name' | 'decision' | 'decided_on' | 'document_version_id'
  >,
): Promise<ComplianceApproval> {
  return write('/admin/compliance-approvals', payload)
}
