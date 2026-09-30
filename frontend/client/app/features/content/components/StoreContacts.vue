<script setup lang="ts">
import { fetchSellerContacts, fetchStores } from '~/services/contentPages'
import PageState from '~/components/shared/PageState.vue'
import SectionHeading from '~/components/shared/SectionHeading.vue'

defineProps<{ title: string }>()
const { requestBase } = usePublicApiConfig()
const {
  data: seller,
  status: sellerStatus,
  error: sellerError,
  refresh: refreshSeller,
} = await useAsyncData('seller-contacts', () =>
  fetchSellerContacts(requestBase),
)
const {
  data: stores,
  status,
  error,
  refresh,
} = await useAsyncData('published-stores', () => fetchStores(requestBase))
const weekdays = [
  'Понедельник',
  'Вторник',
  'Среда',
  'Четверг',
  'Пятница',
  'Суббота',
  'Воскресенье',
]
const hasContacts = computed(
  () =>
    seller.value &&
    (seller.value.seller_name ||
      seller.value.address ||
      seller.value.email ||
      seller.value.phones.length),
)
function phoneUrl(phone: string) {
  return `tel:${phone.replace(/[^+0-9]/g, '')}`
}
</script>

<template>
  <section class="section section--soft">
    <div class="container">
      <SectionHeading eyebrow="AgatCeramic" :title="title" />
      <PageState
        v-if="sellerError || sellerStatus === 'pending'"
        title="Реквизиты продавца"
        :message="
          sellerError
            ? 'Не удалось загрузить контакты продавца.'
            : 'Загружаем контакты…'
        "
        :error="!!sellerError"
        :loading="sellerStatus === 'pending'"
        @retry="refreshSeller()"
      />
      <div v-else-if="hasContacts" class="seller">
        <h3 v-if="seller?.seller_name">{{ seller.seller_name }}</h3>
        <p v-if="seller?.address">{{ seller.address }}</p>
        <a
          v-for="phone in seller?.phones"
          :key="phone"
          :href="phoneUrl(phone)"
          >{{ phone }}</a
        >
        <a v-if="seller?.email" :href="`mailto:${seller.email}`">{{
          seller.email
        }}</a>
      </div>
      <p v-else class="empty-note" role="status">
        Контакты продавца пока не опубликованы.
      </p>
      <PageState
        v-if="error || status === 'pending'"
        title="Магазины"
        :message="
          error
            ? 'Не удалось загрузить магазины и часы работы.'
            : 'Загружаем магазины…'
        "
        :error="!!error"
        :loading="status === 'pending'"
        @retry="refresh()"
      />
      <div v-else-if="stores?.length" class="stores">
        <article v-for="store in stores" :key="store.id" class="store">
          <h3>{{ store.name }}</h3>
          <p>{{ store.address }}</p>
          <a v-if="store.phone" :href="phoneUrl(store.phone)">{{
            store.phone
          }}</a>
          <dl aria-label="Часы работы" class="hours">
            <div
              v-for="day in [...store.working_hours].sort(
                (a, b) => a.weekday - b.weekday,
              )"
              :key="day.weekday"
            >
              <dt>{{ weekdays[day.weekday - 1] }}</dt>
              <dd>
                {{
                  day.is_closed
                    ? 'Выходной'
                    : `${day.opens_at}–${day.closes_at}`
                }}
              </dd>
            </div>
          </dl>
        </article>
      </div>
      <p v-else class="empty-note" role="status">
        Адреса магазинов и часы работы пока не опубликованы.
      </p>
    </div>
  </section>
</template>

<style scoped>
.seller {
  overflow-wrap: anywhere;
  max-width: 680px;
  margin: 0 auto 64px;
  text-align: center;
}
h3 {
  margin: 0 0 16px;
  font-size: 22px;
  font-weight: 500;
}
p {
  color: var(--color-muted);
  line-height: 1.8;
}
a {
  display: block;
  width: fit-content;
  max-width: 100%;
  margin-block: 12px;
  border-bottom: 1px solid var(--color-line);
  padding-block: 8px;
  overflow-wrap: anywhere;
}
.seller a {
  margin-inline: auto;
}
.stores {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 48px;
}
.store {
  border-top: 1px solid var(--color-line);
  padding-top: 32px;
  overflow-wrap: anywhere;
}
.hours {
  margin: 28px 0 0;
  font-size: 13px;
}
.hours div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-block: 10px;
  border-bottom: 1px solid var(--color-line);
}
dd {
  margin: 0;
  text-align: right;
}
.empty-note {
  text-align: center;
}
@media (max-width: 640px) {
  .stores {
    grid-template-columns: 1fr;
  }
}
</style>
