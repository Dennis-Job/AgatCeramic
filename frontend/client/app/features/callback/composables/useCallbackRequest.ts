import { submitCallbackRequest } from '../services/callbackRequests'

export function useCallbackRequest() {
  const { publicBase } = usePublicApiConfig()
  const name = ref('')
  const phone = ref('')
  const busy = ref(false)
  const success = ref(false)
  const error = ref('')

  async function submit() {
    if (busy.value || success.value) return
    busy.value = true
    error.value = ''
    try {
      await submitCallbackRequest(publicBase, {
        name: name.value.trim() || null,
        phone: phone.value.trim(),
      })
      success.value = true
      name.value = ''
      phone.value = ''
    } catch (failure) {
      error.value =
        failure instanceof Error
          ? failure.message
          : 'Не удалось отправить заявку. Попробуйте ещё раз.'
    } finally {
      busy.value = false
    }
  }
  return { name, phone, busy, success, error, submit }
}
