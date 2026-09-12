import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'

export function useProfileEditor() {
  const auth = useAuthStore()
  const router = useRouter()
  const name = ref(auth.user?.name ?? '')
  const email = ref(auth.user?.email ?? '')
  const password = ref('')
  const passwordConfirmation = ref('')
  const error = ref('')
  const success = ref('')
  const isSubmitting = ref(false)

  async function submit(): Promise<void> {
    error.value = ''
    success.value = ''
    isSubmitting.value = true
    const passwordChanged = Boolean(password.value)

    try {
      await auth.updateProfile({
        name: name.value,
        email: email.value,
        ...(passwordChanged ? { password: password.value, password_confirmation: passwordConfirmation.value } : {}),
      })
      if (passwordChanged) {
        await auth.logout()
        await router.replace({ name: 'login', query: { password_changed: '1' } })
        return
      }
      success.value = 'Профиль сохранён.'
    } catch (reason) {
      error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить профиль.'
    } finally {
      isSubmitting.value = false
    }
  }

  return { name, email, password, passwordConfirmation, error, success, isSubmitting, submit }
}
