<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
import { fetchCheckoutLegalDocuments } from '../services/legalDocuments'

defineProps<{ disabled?: boolean }>()
const accepted = defineModel<boolean>({ required: true })
const { publicBase } = usePublicApiConfig()
const consentSection = useTemplateRef<HTMLElement>('consentSection')
const documents = shallowRef<[policy: string, consent: string]>()
const loading = ref(true)

async function loadDocuments() {
  loading.value = true
  try {
    documents.value = await fetchCheckoutLegalDocuments(publicBase)
  } catch {
    documents.value = undefined
  } finally {
    loading.value = false
  }
}
onMounted(loadDocuments)

async function retryDocuments() {
  await loadDocuments()
  await nextTick()
  if (document.activeElement === document.body) {
    consentSection.value
      ?.querySelector<HTMLElement>(documents.value ? 'input' : 'button')
      ?.focus()
  }
}
</script>

<template>
  <div ref="consentSection" class="checkout-consent">
    <label class="checkout-consent__label">
      <input
        v-model="accepted"
        type="checkbox"
        :disabled="disabled || !documents"
        required
      />
      <span>Я даю согласие на обработку персональных данных</span>
    </label>
    <p v-if="loading" role="status">Загружаем документы…</p>
    <template v-else-if="documents">
      <details>
        <summary>Текст согласия</summary>
        <p class="checkout-consent__document">{{ documents[1] }}</p>
      </details>
      <details>
        <summary>Политика конфиденциальности</summary>
        <p class="checkout-consent__document">{{ documents[0] }}</p>
      </details>
    </template>
    <template v-else>
      <p role="alert">Документы недоступны. Попробуйте загрузить их ещё раз.</p>
      <UiButton
        type="button"
        variant="outline"
        size="compact"
        @click="retryDocuments"
      >
        Повторить
      </UiButton>
    </template>
  </div>
</template>

<style scoped>
.checkout-consent {
  display: grid;
  justify-items: start;
  gap: 8px;
  margin-top: 20px;
  font-size: 14px;
  line-height: 1.6;
}
.checkout-consent__label {
  display: flex;
  min-height: 44px;
  align-items: center;
  gap: 12px;
}
input {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--color-ink);
}
summary {
  width: fit-content;
  padding-block: 7px;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 3px;
}
p {
  margin: 0;
}
.checkout-consent__document {
  margin: 8px 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
