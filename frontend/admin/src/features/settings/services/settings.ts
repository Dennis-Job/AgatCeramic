import { apiData } from '../../../services/api'
import type {
  ComplianceApproval,
  LegalDocument,
  SiteSettings,
} from '../types/settings.types'

async function write<T>(
  path: string,
  payload?: object,
  method = 'POST',
): Promise<T> {
  return apiData(path, {
    method,
    ...(payload
      ? {
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        }
      : {}),
  })
}

export async function getSettings(): Promise<SiteSettings> {
  return apiData('/admin/site-settings')
}

export async function saveSettings(
  payload: Omit<SiteSettings, 'updated_at'>,
): Promise<SiteSettings> {
  return write('/admin/site-settings', payload, 'PATCH')
}

export async function getLegalDocuments(): Promise<LegalDocument[]> {
  return apiData('/admin/legal-documents')
}

export async function createLegalDocument(
  payload: Pick<LegalDocument, 'type' | 'version' | 'body' | 'content'>,
): Promise<LegalDocument> {
  return write('/admin/legal-documents', payload)
}

export async function publishLegalDocument(id: number): Promise<LegalDocument> {
  return write(`/admin/legal-documents/${id}/publish`)
}

export async function updateLegalDocument(
  id: number,
  payload: Pick<LegalDocument, 'type' | 'version' | 'body' | 'content'>,
): Promise<LegalDocument> {
  return write(`/admin/legal-documents/${id}`, payload, 'PATCH')
}

export async function getComplianceApprovals(): Promise<ComplianceApproval[]> {
  return apiData('/admin/compliance-approvals')
}

export async function createComplianceApproval(
  payload: Pick<
    ComplianceApproval,
    'role' | 'reviewer_name' | 'decision' | 'decided_on' | 'document_version_id'
  >,
): Promise<ComplianceApproval> {
  return write('/admin/compliance-approvals', payload)
}
