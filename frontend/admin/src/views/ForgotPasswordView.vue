<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthCard from '../components/shared/AuthCard.vue'
import UiAlert from '../components/ui/UiAlert.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiField from '../components/ui/UiField.vue'
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
  <AuthCard title="Восстановление пароля" description="Укажите email сотрудника, и мы отправим ссылку для сброса пароля." @submit="submit">
    <UiAlert v-if="error" class="mt-5">{{ error }}</UiAlert>
    <UiAlert v-if="isSent" class="mt-5" tone="success" live="polite">Если такая учётная запись существует, ссылка для сброса пароля отправлена.</UiAlert>
    <UiField class="mt-6" label="Email" required><UiInput v-model="email" class="mt-1.5 w-full" type="email" autocomplete="email" autofocus required :disabled="isSubmitting" /></UiField>
    <UiButton class="mt-6 w-full" type="submit" :loading="isSubmitting" :disabled="isSubmitting || isSent">{{ isSubmitting ? 'Отправка…' : 'Отправить ссылку' }}</UiButton>
    <RouterLink class="mt-5 block rounded text-center text-sm font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2" :to="{ name: 'login' }">Вернуться ко входу</RouterLink>
  </AuthCard>
</template>
