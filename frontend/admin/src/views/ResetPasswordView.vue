<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseInput from '../components/BaseInput.vue'
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
  <main class="grid min-h-screen place-items-center bg-[#f7f9fc] p-4">
    <form class="w-full max-w-md rounded-2xl border border-[#e4e7ec] bg-white p-8 shadow-[0_8px_24px_rgba(16,24,40,.08)]" @submit.prevent="submit">
      <p class="text-sm font-semibold text-[#7f56d9]">AgatCeramic</p>
      <h1 class="mt-3 text-2xl font-bold text-[#101828]">Задайте новый пароль</h1>
      <p class="mt-2 text-sm text-[#667085]">Используйте надёжный пароль длиной не менее 12 символов.</p>
      <p v-if="error" class="mt-5 rounded-lg bg-[#fef3f2] px-3 py-2 text-sm text-[#b42318]" role="alert">{{ error }}</p>
      <label class="mt-6 block text-sm font-medium text-[#344054]">Email<BaseInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" required /></label>
      <label class="mt-4 block text-sm font-medium text-[#344054]">Новый пароль<BaseInput v-model="password" class="mt-1.5 w-full" type="password" autocomplete="new-password" minlength="12" required /></label>
      <label class="mt-4 block text-sm font-medium text-[#344054]">Подтверждение пароля<BaseInput v-model="passwordConfirmation" class="mt-1.5 w-full" type="password" autocomplete="new-password" minlength="12" required /></label>
      <button class="mt-6 w-full rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Сброс пароля…' : 'Сбросить пароль' }}</button>
      <RouterLink class="mt-5 block text-center text-sm font-semibold text-[#6941c6] hover:text-[#53389e]" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
    </form>
  </main>
</template>
