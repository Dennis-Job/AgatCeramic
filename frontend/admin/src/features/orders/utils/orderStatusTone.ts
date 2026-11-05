export function orderStatusTone(status: string) {
  if (status === 'new') return 'success' as const
  if (status === 'processing') return 'additional' as const
  if (status === 'cancelled') return 'danger' as const
  if (status === 'confirmed' || status === 'shipped') return 'warning' as const
  return 'neutral' as const
}
