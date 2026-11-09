export function orderStatusTone(status: string) {
  if (status === 'new') return 'success' as const
  if (
    ['processing', 'awaiting_clarification', 'picking', 'working'].includes(
      status,
    )
  )
    return 'warning' as const
  if (status === 'cancelled') return 'danger' as const
  if (status === 'confirmed' || status === 'shipped')
    return 'additional' as const
  return 'neutral' as const
}
