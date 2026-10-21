export function useSuccessNotification() {
  const notification = useState<{ message: string } | null>(
    'success-notification',
    () => null,
  )

  function showSuccess(message: string) {
    notification.value = { message }
  }

  function dismiss() {
    notification.value = null
  }

  return { notification: readonly(notification), showSuccess, dismiss }
}
