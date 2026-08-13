<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseInput from '../components/BaseInput.vue'
import { useAuthStore } from '../stores/auth'

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
</script>

<template>
  <section class="mx-auto max-w-2xl">
    <div class="mb-7">
      <p class="text-sm font-medium text-[#667085]">Учётная запись</p>
      <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#101828] sm:text-3xl">Мой профиль</h1>
      <p class="mt-2 text-sm text-[#667085]">Изменяйте только свои имя, email и пароль.</p>
    </div>

    <form class="rounded-xl border border-[#e4e7ec] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,.04)] sm:p-6" @submit.prevent="submit">
      <p v-if="error" class="mb-5 rounded-lg border border-[#fecdca] bg-[#fef3f2] px-4 py-3 text-sm text-[#b42318]" role="alert">{{ error }}</p>
      <p v-if="success" class="mb-5 rounded-lg bg-[#ecfdf3] px-4 py-3 text-sm text-[#027a48]" role="status">{{ success }}</p>
      <div class="grid gap-4">
        <label class="text-sm font-medium text-[#344054]">Имя<BaseInput v-model="name" class="mt-1.5 w-full font-normal" required /></label>
        <label class="text-sm font-medium text-[#344054]">Email<BaseInput v-model="email" class="mt-1.5 w-full font-normal" type="email" required /></label>
        <div class="border-t border-[#eaecf0] pt-5">
          <h2 class="text-base font-semibold text-[#344054]">Смена пароля</h2>
          <p class="mt-1 text-sm text-[#667085]">Оставьте поля пустыми, если менять пароль не нужно.</p>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-[#344054]">Новый пароль<BaseInput v-model="password" class="mt-1.5 w-full font-normal" type="password" autocomplete="new-password" minlength="12" /></label>
            <label class="text-sm font-medium text-[#344054]">Подтверждение<BaseInput v-model="passwordConfirmation" class="mt-1.5 w-full font-normal" type="password" autocomplete="new-password" minlength="12" :required="Boolean(password)" /></label>
          </div>
        </div>
      </div>
      <div class="mt-6 flex justify-end"><button class="rounded-lg bg-[#7f56d9] px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Сохранение…' : 'Сохранить' }}</button></div>
    </form>
  </section>
</template>
