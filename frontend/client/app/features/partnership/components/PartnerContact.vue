<script setup lang="ts">
import { fetchSellerContacts } from '~/services/contentPages'
import PageState from '~/components/shared/PageState.vue'

const { requestBase } = usePublicApiConfig()
const {
  data: seller,
  status,
  error,
  refresh,
} = await useAsyncData('seller-contacts', () =>
  fetchSellerContacts(requestBase),
)
</script>

<template>
  <PageState
    v-if="error || status === 'pending'"
    title="Связаться по email"
    :message="
      error
        ? 'Не удалось загрузить email для сотрудничества.'
        : 'Загружаем контакт…'
    "
    :error="!!error"
    :loading="status === 'pending'"
    @retry="refresh()"
  />
  <div v-else-if="seller?.partner_email" class="partner-contact">
    <h3>Связаться по email</h3>
    <a :href="`mailto:${seller.partner_email}`">{{ seller.partner_email }}</a>
  </div>
  <p v-else class="empty-note" role="status">
    Email для сотрудничества пока не опубликован. Вы можете отправить
    предложение через форму.
  </p>
</template>

<style scoped>
.partner-contact,
.empty-note {
  overflow-wrap: anywhere;
}
h3 {
  margin: 0 0 16px;
  font-size: 22px;
  font-weight: 500;
}
a {
  display: block;
  width: fit-content;
  max-width: 100%;
  border-bottom: 1px solid var(--color-line);
  padding-block: 8px;
  line-height: 1.8;
}
.empty-note {
  margin: 0;
  color: var(--color-muted);
  line-height: 1.8;
}
</style>
