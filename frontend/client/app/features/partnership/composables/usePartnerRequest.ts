import { submitPartnerRequest } from '../services/partnerRequests'

export function usePartnerRequest() {
  const { publicBase } = usePublicApiConfig()
  const name = ref('')
  const phone = ref('')
  const email = ref('')
  const message = ref('')
  const busy = ref(false)
  const success = ref(false)
  const error = ref('')

  async function submit() {
    if (busy.value || success.value) return
    busy.value = true
    error.value = ''
    try {
      await submitPartnerRequest(publicBase, {
        name: name.value.trim(),
        phone: phone.value.trim(),
        email: email.value.trim(),
        message: message.value.trim(),
      })
      success.value = true
      name.value = ''
      phone.value = ''
      email.value = ''
      message.value = ''
    } catch (failure) {
      error.value =
        failure instanceof Error
          ? failure.message
          : 'Не удалось отправить предложение. Попробуйте ещё раз.'
    } finally {
      busy.value = false
    }
  }
  return { name, phone, email, message, busy, success, error, submit }
}
