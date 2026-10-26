import { inject, type InjectionKey } from 'vue'
import type { AdminNotificationState } from './useAdminNotifications'

export const adminNotificationKey: InjectionKey<AdminNotificationState> =
  Symbol('adminNotifications')

export function useNotificationContext(): AdminNotificationState {
  const state = inject(adminNotificationKey)
  if (!state) throw new Error('Notifications require AdminLayout')
  return state
}
