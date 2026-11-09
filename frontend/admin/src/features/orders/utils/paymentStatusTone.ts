export function paymentStatusTone(status: string) {
  if (status === 'refunded') return 'danger' as const
  if (status === 'paid') return 'success' as const
  if (status === 'partially_paid') return 'additional' as const
  if (status === 'pending' || status === 'not_paid') return 'warning' as const
  return 'neutral' as const
}
