<script setup lang="ts">
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import { useProfileEditor } from '../composables/useProfileEditor'

const { name, email, password, passwordConfirmation, error, success, isSubmitting, submit } = useProfileEditor()
</script>

<template>
  <section class="mx-auto max-w-2xl">
    <PageHeader class="mb-7" eyebrow="Учётная запись" title="Мой профиль" description="Изменяйте только свои имя, email и пароль." />
    <form @submit.prevent="submit"><UiCard class="p-5 sm:p-6">
      <UiAlert v-if="error" class="mb-5">{{ error }}</UiAlert>
      <UiAlert v-if="success" class="mb-5" tone="success" live="polite">{{ success }}</UiAlert>
      <div class="grid gap-4">
        <UiField label="Имя" required><UiInput v-model="name" class="mt-1.5 w-full font-normal" required :disabled="isSubmitting" /></UiField>
        <UiField label="Email" required><UiInput v-model="email" class="mt-1.5 w-full font-normal" type="email" required :disabled="isSubmitting" /></UiField>
        <section class="border-t border-gray-100 pt-5" aria-labelledby="profile-password-title">
          <h2 id="profile-password-title" class="text-base font-semibold text-gray-700">Смена пароля</h2>
          <p class="mt-1 text-sm text-gray-500">Оставьте поля пустыми, если менять пароль не нужно.</p>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <UiField label="Новый пароль"><UiInput v-model="password" class="mt-1.5 w-full font-normal" type="password" autocomplete="new-password" minlength="12" :disabled="isSubmitting" /></UiField>
            <UiField label="Подтверждение"><UiInput v-model="passwordConfirmation" class="mt-1.5 w-full font-normal" type="password" autocomplete="new-password" minlength="12" :required="Boolean(password)" :disabled="isSubmitting" /></UiField>
          </div>
        </section>
      </div>
      <div class="mt-6 flex justify-end"><UiButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Сохранение…' : 'Сохранить' }}</UiButton></div>
    </UiCard></form>
  </section>
</template>
