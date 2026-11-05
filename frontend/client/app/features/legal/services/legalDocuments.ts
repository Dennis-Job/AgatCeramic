export type LegalDocumentType = 'privacy_policy' | 'consent'

export async function fetchPublishedDocument(
  base: string,
  type: LegalDocumentType,
) {
  const { data } = await $fetch<{
    data: { type: LegalDocumentType; body: string; published_at: string }
  }>(`${base}/legal-documents/${type}`, {
    cache: 'no-store',
    credentials: 'omit',
    timeout: 10000,
  })
  if (data?.type !== type || !data.published_at || !data.body?.trim())
    throw new Error('Published legal document unavailable')
  return data.body
}

export function fetchCheckoutLegalDocuments(base: string) {
  return Promise.all([
    fetchPublishedDocument(base, 'privacy_policy'),
    fetchPublishedDocument(base, 'consent'),
  ])
}
