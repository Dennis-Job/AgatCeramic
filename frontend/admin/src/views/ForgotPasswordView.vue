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
  <main class="grid min-h-screen place-items-center bg-[#f7f9fc] p-4">
    <form class="w-full max-w-md rounded-2xl border border-[#e4e7ec] bg-white p-8 shadow-[0_8px_24px_rgba(16,24,40,.08)]" @submit.prevent="submit">
      <p class="text-sm font-semibold text-[#7f56d9]">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-[#101828]">Восстановление пароля</h1>
      <p class="mt-2 text-sm text-[#667085]">Укажите email сотрудника, и мы отправим ссылку для сброса пароля.</p>
      <p v-if="error" class="mt-5 rounded-lg bg-[#fef3f2] px-3 py-2 text-sm text-[#b42318]" role="alert">{{ error }}</p>
      <p v-if="isSent" class="mt-5 rounded-lg bg-[#ecfdf3] px-3 py-2 text-sm text-[#027a48]" role="status">Если такая учётная запись существует, ссылка для сброса пароля отправлена.</p>
      <label class="mt-6 block text-sm font-medium text-[#344054]">Email<BaseInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" required :disabled="isSubmitting" /></label>
      <button class="mt-6 w-full rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting || isSent">{{ isSubmitting ? 'Отправка…' : 'Отправить ссылку' }}</button>
      <RouterLink class="mt-5 block text-center text-sm font-semibold text-[#6941c6] hover:text-[#53389e]" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
    </form>
  </main>
</template>
