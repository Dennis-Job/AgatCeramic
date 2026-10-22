export function contactStatusTone(status: string) {
  if (status === 'completed') return 'neutral' as const
  if (status === 'rejected') return 'danger' as const
  if (status === 'awaiting_client') return 'warning' as const
  if (status === 'new') return 'success' as const
  if (status === 'processing') return 'additional' as const
  return 'neutral' as const
}
