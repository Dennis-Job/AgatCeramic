import { computed, shallowRef } from 'vue'

const dialogs = shallowRef<Array<{ owner: symbol; panel: HTMLElement }>>([])
export const notificationOpener = shallowRef<HTMLElement | null>(null)
export const notificationDialog = computed(() => dialogs.value.at(-1)?.panel)

export function registerNotificationDialog(
  owner: symbol,
  panel: HTMLElement,
): void {
  if (dialogs.value.some((dialog) => dialog.owner === owner)) return
  dialogs.value = [...dialogs.value, { owner, panel }]
}
export function unregisterNotificationDialog(owner: symbol): void {
  dialogs.value = dialogs.value.filter((dialog) => dialog.owner !== owner)
}
