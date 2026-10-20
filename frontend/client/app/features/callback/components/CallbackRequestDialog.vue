<script setup lang="ts">
import UiDialog from '~/components/ui/UiDialog.vue'
import UiTextField from '~/components/ui/UiTextField.vue'
import UiButton from '~/components/ui/UiButton.vue'
import { useCallbackRequest } from '../composables/useCallbackRequest'

const emit = defineEmits<{ close: [] }>()
const { name, phone, busy, success, error, submit } = useCallbackRequest()
</script>

<template>
  <UiDialog title="Заказать звонок" :busy="busy" @close="emit('close')">
    <div v-if="success" class="callback-result">
      <p role="status">
        Заявка отправлена. Мы перезвоним вам по указанному номеру.
      </p>
      <UiButton @click="emit('close')">Готово</UiButton>
    </div>
    <form
      v-else
      class="callback-form"
      :aria-busy="busy"
      @submit.prevent="submit"
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
.callback-form,
.callback-result {
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
