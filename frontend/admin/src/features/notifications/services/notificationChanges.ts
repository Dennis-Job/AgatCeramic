// SPA-local invalidation only: no customer data, counts or session state are stored here.
const listeners = new Set<() => void>()

export function notifyAdminNotificationsChanged(): void {
  listeners.forEach((listener) => listener())
}

export function subscribeAdminNotificationChanges(
  listener: () => void,
): () => void {
  listeners.add(listener)
  return () => {
    listeners.delete(listener)
  }
}
