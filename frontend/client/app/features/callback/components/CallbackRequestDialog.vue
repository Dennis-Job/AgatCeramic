<script setup lang="ts">
import UiDialog from '~/components/ui/UiDialog.vue'
import UiTextField from '~/components/ui/UiTextField.vue'
import UiButton from '~/components/ui/UiButton.vue'
import { useCallbackRequest } from '../composables/useCallbackRequest'

const emit = defineEmits<{ close: [] }>()
const { name, phone, busy, success, error, submit } = useCallbackRequest()
const { showSuccess } = useSuccessNotification()

async function submitRequest(close: () => void) {
  await submit()
  if (success.value) close()
}

function finishClose() {
  if (success.value) {
    showSuccess('Заявка отправлена. Мы перезвоним вам по указанному номеру.')
  }
  emit('close')
}
</script>

<template>
  <UiDialog
    v-slot="{ close }"
    title="Заказать звонок"
    :busy="busy"
    @close="finishClose"
  >
    <form
      class="callback-form"
      :aria-busy="busy"
      @submit.prevent="submitRequest(close)"
    >
      <p class="callback-description">
        Оставьте номер телефона, и сотрудник магазина свяжется с вами. Имя можно
        не указывать.
      </p>
      <UiTextField
        v-model="phone"
        label="Телефон"
        type="tel"
        autocomplete="tel"
        required
        :disabled="busy"
      />
      <UiTextField
        v-model="name"
        label="Ваше имя"
        autocomplete="name"
        :maxlength="255"
        :disabled="busy"
      />
      <p v-if="error" class="callback-error" role="alert">{{ error }}</p>
      <p v-if="busy" class="callback-description" role="status">
        Отправляем заявку…
      </p>
      <UiButton type="submit" :disabled="busy">{{
        busy ? 'Отправляем…' : 'Отправить заявку'
      }}</UiButton>
    </form>
  </UiDialog>
</template>

<style scoped>
.callback-form {
  display: grid;
  gap: 24px;
}
p {
  margin: 0;
  font-size: 14px;
  line-height: 1.7;
}
.callback-description {
  color: var(--color-muted);
}
.callback-error {
  border-left: 2px solid var(--color-ink);
  padding-left: 12px;
}
</style>
