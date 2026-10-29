<script setup lang="ts">
import SiteHeader from '~/components/shared/SiteHeader.vue'
import CallbackRequestDialog from './CallbackRequestDialog.vue'
import { fetchSellerContacts } from '~/services/contentPages'
import type { HomePageContent, SiteLink } from '~/types/homePage'
import { formatPhone, normalizePhoneInput } from '~/utils/phone'

defineProps<{
  content?: HomePageContent['header']
  socialLinks?: SiteLink[]
  currentPath?: string
  navigationLabel?: string
}>()
const { requestBase } = usePublicApiConfig()
const { data: seller } = await useAsyncData('seller-contacts', () =>
  fetchSellerContacts(requestBase),
)
const phone = computed(() => seller.value?.phones[0])
const phoneHref = computed(() =>
  phone.value && /^\+7\d{10}$/.test(normalizePhoneInput(phone.value) ?? '')
    ? `tel:${normalizePhoneInput(phone.value)}`
    : undefined,
)
const callbackOpen = ref(false)
const route = useRoute()
watch(
  () => route.fullPath,
  () => {
    callbackOpen.value = false
  },
)
</script>

<template>
  <SiteHeader
    :content="content"
    :phone="formatPhone(phone)"
    :phone-href="phoneHref"
    :email="seller?.email"
    :social-links="socialLinks"
    :current-path="currentPath"
    :navigation-label="navigationLabel"
  >
    <template #topbar-right>
      <div class="store-contacts">
        <button
          type="button"
          aria-haspopup="dialog"
          @click="callbackOpen = true"
        >
          заказать звонок
        </button>
        <span v-if="seller?.email" class="store-contacts__item">
          <span class="store-contacts__separator" aria-hidden="true">·</span>
          <a :href="`mailto:${seller.email}`">{{ seller.email }}</a>
        </span>
        <span v-if="phone" class="store-contacts__item">
          <span class="store-contacts__separator" aria-hidden="true">·</span>
          <a :href="phoneHref">{{ formatPhone(phone) }}</a>
        </span>
      </div>
    </template>
  </SiteHeader>
  <CallbackRequestDialog v-if="callbackOpen" @close="callbackOpen = false" />
</template>

<style scoped>
.store-contacts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0 12px;
  min-width: 0;
  margin-inline-start: auto;
}
.store-contacts__item {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  max-width: 100%;
}
.store-contacts__separator {
  flex: none;
}
.store-contacts button,
.store-contacts a {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  min-width: 0;
  max-width: 100%;
  overflow-wrap: anywhere;
  padding: 8px 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  letter-spacing: inherit;
}
.store-contacts button {
  text-decoration: none;
}
.store-contacts a:hover,
.store-contacts button:hover {
  color: var(--color-ink);
}
</style>
