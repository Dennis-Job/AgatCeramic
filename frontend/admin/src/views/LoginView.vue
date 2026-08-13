<script setup lang="ts">
import { ref } from 'vue'
import BaseInput from '../components/BaseInput.vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
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
  <main class="grid min-h-screen place-items-center bg-[#f7f9fc] p-4">
    <form class="w-full max-w-md rounded-2xl border border-[#e4e7ec] bg-white p-8 shadow-[0_8px_24px_rgba(16,24,40,.08)]" @submit.prevent="submit">
      <p class="text-sm font-semibold text-[#7f56d9]">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-[#101828]">Вход в админ-панель</h1>
      <p class="mt-2 text-sm text-[#667085]">Используйте учётную запись сотрудника.</p>

      <p v-if="error" class="mt-5 rounded-lg bg-[#fef3f2] px-3 py-2 text-sm text-[#b42318]" role="alert">{{ error }}</p>

      <label class="mt-6 block text-sm font-medium text-[#344054]">
        Email
        <BaseInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="username" required />
      </label>
      <label class="mt-4 block text-sm font-medium text-[#344054]">
        Пароль
        <BaseInput v-model="password" class="mt-1.5 w-full" type="password" autocomplete="current-password" required />
      </label>
      <button class="mt-6 w-full rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting">
        {{ isSubmitting ? 'Выполняется вход…' : 'Войти' }}
      </button>
    </form>
  </main>
</template>
