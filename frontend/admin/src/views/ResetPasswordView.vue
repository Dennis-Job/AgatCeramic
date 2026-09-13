<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthCard from '../components/shared/AuthCard.vue'
import UiAlert from '../components/ui/UiAlert.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiField from '../components/ui/UiField.vue'
import UiInput from '../components/ui/UiInput.vue'
import { resetPassword } from '../services/auth'

const route = useRoute()
const router = useRouter()
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const token = computed(() => typeof route.query.token === 'string' ? route.query.token : '')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const isSubmitting = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  if (!token.value) {
    error.value = 'Ссылка для сброса пароля недействительна или неполная.'
    return
  }
  isSubmitting.value = true
  try {
    await resetPassword({ email: email.value, token: token.value, password: password.value, password_confirmation: passwordConfirmation.value })
    await router.replace({ name: 'login', query: { password_reset: '1' } })
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Не удалось сбросить пароль.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AuthCard title="Задайте новый пароль" description="Используйте надёжный пароль длиной не менее 12 символов." @submit="submit">
    <UiAlert v-if="error" class="mt-5">{{ error }}</UiAlert>
    <UiField class="mt-6" label="Email" required><UiInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" :autofocus="!email" required /></UiField>
    <UiField class="mt-4" label="Новый пароль" required><UiInput v-model="password" class="mt-1.5 w-full" type="password" autocomplete="new-password" :autofocus="Boolean(email)" minlength="12" required /></UiField>
    <UiField class="mt-4" label="Подтверждение пароля" required><UiInput v-model="passwordConfirmation" class="mt-1.5 w-full" type="password" autocomplete="new-password" minlength="12" required /></UiField>
    <UiButton class="mt-6 w-full" type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Сброс пароля…' : 'Сбросить пароль' }}</UiButton>
    <RouterLink class="mt-5 block rounded text-center text-sm font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
  </AuthCard>
</template>
