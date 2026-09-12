<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiAlert from '../components/ui/UiAlert.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiInput from '../components/ui/UiInput.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const error = ref('')
const isSubmitting = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  isSubmitting.value = true

  try {
    await auth.login(email.value, password.value)
    await router.replace('/')
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Не удалось выполнить вход.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <form class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-dialog" @submit.prevent="submit">
      <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-gray-900">Вход в админ-панель</h1>
      <p class="mt-2 text-sm text-gray-500">Используйте учётную запись сотрудника.</p>

      <UiAlert v-if="error" class="mt-5">{{ error }}</UiAlert>
      <UiAlert v-if="route.query.password_reset === '1' || route.query.password_changed === '1'" class="mt-5" tone="success" live="polite">Пароль изменён. Теперь войдите с новым паролем.</UiAlert>

      <label class="mt-6 block text-sm font-medium text-gray-700">
        Email
        <UiInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="username" required />
      </label>
      <label class="mt-4 block text-sm font-medium text-gray-700">
        Пароль
        <UiInput v-model="password" class="mt-1.5 w-full" type="password" autocomplete="current-password" required />
      </label>
      <RouterLink class="mt-3 inline-block text-sm font-semibold text-primary-600 hover:text-primary-700" :to="{ name: 'forgot-password' }">Забыли пароль?</RouterLink>
      <UiButton class="mt-6 w-full" type="submit" :loading="isSubmitting" :disabled="isSubmitting">
        {{ isSubmitting ? 'Выполняется вход…' : 'Войти' }}
      </UiButton>
  </form>
</template>
