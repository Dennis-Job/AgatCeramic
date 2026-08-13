<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import BaseInput from '../components/BaseInput.vue'
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
  <main class="grid min-h-screen place-items-center bg-page p-4">
    <form class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-dialog" @submit.prevent="submit">
      <p class="text-sm font-semibold text-primary-500">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-gray-900">Восстановление пароля</h1>
      <p class="mt-2 text-sm text-gray-500">Укажите email сотрудника, и мы отправим ссылку для сброса пароля.</p>
      <p v-if="error" class="mt-5 rounded-lg bg-error-50 px-3 py-2 text-sm text-error-500" role="alert">{{ error }}</p>
      <p v-if="isSent" class="mt-5 rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700" role="status">Если такая учётная запись существует, ссылка для сброса пароля отправлена.</p>
      <label class="mt-6 block text-sm font-medium text-gray-700">Email<BaseInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" required :disabled="isSubmitting" /></label>
      <button class="mt-6 w-full rounded-lg bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting || isSent">{{ isSubmitting ? 'Отправка…' : 'Отправить ссылку' }}</button>
      <RouterLink class="mt-5 block text-center text-sm font-semibold text-primary-600 hover:text-primary-700" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
    </form>
  </main>
</template>
