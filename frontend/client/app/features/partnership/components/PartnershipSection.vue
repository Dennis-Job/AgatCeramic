<script setup lang="ts">
import UiTextField from '~/components/ui/UiTextField.vue'
import UiTextarea from '~/components/ui/UiTextarea.vue'
import UiButton from '~/components/ui/UiButton.vue'
import PartnerContact from './PartnerContact.vue'
import { usePartnerRequest } from '../composables/usePartnerRequest'

const { name, phone, email, message, busy, success, error, submit } =
  usePartnerRequest()
const result = ref<HTMLElement | null>(null)
watch(success, async (received) => {
  if (!received) return
  await nextTick()
  result.value?.scrollIntoView({ block: 'center', behavior: 'instant' })
})
</script>

<template>
  <section class="section section--soft" aria-labelledby="partnership-title">
    <div class="container partnership">
      <div class="partnership-intro">
        <h2 id="partnership-title">Предложить сотрудничество</h2>
        <p class="description">
          Расскажите о вашем предложении. Укажите контакты, чтобы мы могли с
          вами связаться. Все поля формы обязательны.
        </p>
        <PartnerContact />
      </div>
      <p v-if="success" ref="result" class="partnership-result" role="status">
        Предложение получено. Мы рассмотрим его и свяжемся с вами по указанным
        контактам.
      </p>
      <form
        v-else
        class="partnership-form"
        aria-label="Предложить сотрудничество"
        :aria-busy="busy"
        @submit.prevent="submit"
      >
        <UiTextField
          v-model="name"
          label="Ваше имя"
          autocomplete="name"
          required
          :maxlength="255"
          :disabled="busy"
        />
        <UiTextField
          v-model="phone"
          label="Телефон"
          type="tel"
          autocomplete="tel"
          required
          :disabled="busy"
        />
        <UiTextField
          v-model="email"
          label="Email"
          type="email"
          autocomplete="email"
          required
          :maxlength="255"
          :disabled="busy"
        />
        <UiTextarea
          v-model="message"
          label="Ваше предложение"
          required
          :maxlength="2000"
          :disabled="busy"
        />
        <p v-if="error" class="partnership-error" role="alert">{{ error }}</p>
        <p v-if="busy" class="description" role="status">
          Отправляем предложение…
        </p>
        <UiButton type="submit" :disabled="busy">{{
          busy ? 'Отправляем…' : 'Отправить предложение'
        }}</UiButton>
      </form>
    </div>
  </section>
</template>

<style scoped>
.partnership {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 64px;
  align-items: start;
}
.partnership-intro,
.partnership-form {
  min-width: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 24px;
}
h2 {
  margin: 0;
  font-size: clamp(28px, 3vw, 40px);
  font-weight: 500;
  line-height: 1.15;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}
p {
  margin: 0;
  font-size: 14px;
  line-height: 1.8;
  overflow-wrap: anywhere;
}
.description {
  color: var(--color-muted);
}
.partnership-error {
  border-left: 2px solid var(--color-ink);
  padding-left: 12px;
}
@media (max-width: 760px) {
  .partnership {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}
</style>
