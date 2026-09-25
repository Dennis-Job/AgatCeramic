export type SiteSettings = {
  operator_type: 'individual_entrepreneur' | 'legal_entity' | null
  seller_name: string | null
  entrepreneur_name: string | null
  inn: string | null
  ogrnip: string | null
  address: string | null
  phones: string[]
  email: string | null
  bank_name: string | null
  bank_bik: string | null
  bank_account: string | null
  bank_correspondent_account: string | null
  publish_bank_details: boolean
  updated_at: string | null
}

export type LegalDocument = {
  id: number
  type: 'offer' | 'privacy_policy' | 'consent'
  version: string
  body: string
  published_at: string | null
  created_at: string
}

export type ComplianceApproval = {
  id: number
  role: 'business_owner' | 'data_protection_officer' | 'legal_reviewer'
  reviewer_name: string
  decision: 'approved' | 'rejected'
  decided_on: string
  document_version_id: number
  recorded_by: number
  created_at: string
}
