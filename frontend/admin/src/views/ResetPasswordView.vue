<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import UiAlert from '../components/ui/UiAlert.vue'
import UiButton from '../components/ui/UiButton.vue'
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
  <form class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-dialog" @submit.prevent="submit">
      <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-gray-900">Задайте новый пароль</h1>
      <p class="mt-2 text-sm text-gray-500">Используйте надёжный пароль длиной не менее 12 символов.</p>
      <UiAlert v-if="error" class="mt-5">{{ error }}</UiAlert>
      <label class="mt-6 block text-sm font-medium text-gray-700">Email<UiInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" required /></label>
      <label class="mt-4 block text-sm font-medium text-gray-700">Новый пароль<UiInput v-model="password" class="mt-1.5 w-full" type="password" autocomplete="new-password" minlength="12" required /></label>
      <label class="mt-4 block text-sm font-medium text-gray-700">Подтверждение пароля<UiInput v-model="passwordConfirmation" class="mt-1.5 w-full" type="password" autocomplete="new-password" minlength="12" required /></label>
      <UiButton class="mt-6 w-full" type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Сброс пароля…' : 'Сбросить пароль' }}</UiButton>
      <RouterLink class="mt-5 block text-center text-sm font-semibold text-primary-600 hover:text-primary-700" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
  </form>
</template>
