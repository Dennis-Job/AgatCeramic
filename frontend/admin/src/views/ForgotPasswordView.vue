<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import UiAlert from '../components/ui/UiAlert.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiInput from '../components/ui/UiInput.vue'
import { requestPasswordReset } from '../services/auth'

const email = ref('')
const error = ref('')
const isSubmitting = ref(false)
const isSent = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  isSubmitting.value = true
  try {
    await requestPasswordReset(email.value)
    isSent.value = true
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Не удалось отправить ссылку для сброса пароля.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <form class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-dialog" @submit.prevent="submit">
      <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-gray-900">Восстановление пароля</h1>
      <p class="mt-2 text-sm text-gray-500">Укажите email сотрудника, и мы отправим ссылку для сброса пароля.</p>
      <UiAlert v-if="error" class="mt-5">{{ error }}</UiAlert>
      <UiAlert v-if="isSent" class="mt-5" tone="success" live="polite">Если такая учётная запись существует, ссылка для сброса пароля отправлена.</UiAlert>
      <label class="mt-6 block text-sm font-medium text-gray-700">Email<UiInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" required :disabled="isSubmitting" /></label>
      <UiButton class="mt-6 w-full" type="submit" :loading="isSubmitting" :disabled="isSubmitting || isSent">{{ isSubmitting ? 'Отправка…' : 'Отправить ссылку' }}</UiButton>
      <RouterLink class="mt-5 block text-center text-sm font-semibold text-primary-600 hover:text-primary-700" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
  </form>
</template>
