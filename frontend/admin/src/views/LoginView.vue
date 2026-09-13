<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthCard from '../components/shared/AuthCard.vue'
import UiAlert from '../components/ui/UiAlert.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiField from '../components/ui/UiField.vue'
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
  <AuthCard title="Вход в админ-панель" description="Используйте учётную запись сотрудника." @submit="submit">
    <UiAlert v-if="error" class="mt-5">{{ error }}</UiAlert>
    <UiAlert v-if="route.query.password_reset === '1' || route.query.password_changed === '1'" class="mt-5" tone="success" live="polite">Пароль изменён. Теперь войдите с новым паролем.</UiAlert>
    <UiField class="mt-6" label="Email" required><UiInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="username" autofocus required /></UiField>
    <UiField class="mt-4" label="Пароль" required><UiInput v-model="password" class="mt-1.5 w-full" type="password" autocomplete="current-password" required /></UiField>
    <RouterLink class="mt-3 inline-block rounded text-sm font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2" :to="{ name: 'forgot-password' }">Забыли пароль?</RouterLink>
    <UiButton class="mt-6 w-full" type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Выполняется вход…' : 'Войти' }}</UiButton>
  </AuthCard>
</template>
