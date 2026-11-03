<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
import UiTextField from '~/components/ui/UiTextField.vue'
import UiTextarea from '~/components/ui/UiTextarea.vue'
import { useCart } from '~/features/cart/composables/useCart'
import { formatMoney } from '~/utils/formatMoney'
import { formatPhone } from '~/utils/phone'
import CheckoutConfirmation from './CheckoutConfirmation.vue'
import CheckoutHeading from './CheckoutHeading.vue'
import CheckoutLayout from './CheckoutLayout.vue'
import CheckoutSection from './CheckoutSection.vue'
import CheckoutSummary from './CheckoutSummary.vue'
import {
  CheckoutError,
  type OrderDetails,
  type OrderReceipt,
} from '../services/orders'

const {
  items,
  total,
  itemCount,
  busy,
  error: cartError,
  loadCart,
  placeOrder,
} = useCart()
const ready = ref(false)
const step = ref<1 | 2 | 3>(1)
const form = ref<HTMLFormElement | null>(null)
const notice = ref<HTMLElement | null>(null)
const confirmation = ref<HTMLElement | null>(null)
const error = ref('')
const sending = ref(false)
const receipt = shallowRef<OrderReceipt>()
const emailProvided = ref(false)
const name = ref('')
const phone = ref('')
const email = ref('')
const address = ref('')
const comment = ref('')
let attempt: { key: string; body: string } | undefined

async function readCart() {
  await loadCart()
  ready.value = true
}
onMounted(readCart)

async function goToStep(next: 1 | 2 | 3) {
  step.value = next
  await nextTick()
  form.value?.querySelector<HTMLElement>('[data-checkout-active]')?.focus()
}

async function advance() {
  if (busy.value || sending.value) return
  error.value = ''
  if (step.value === 1) return goToStep(2)
  if (step.value === 2) return goToStep(3)
  const details: OrderDetails = {
    customer_name: name.value.trim(),
    customer_phone: phone.value,
    customer_email: email.value.trim() || null,
    delivery_address: address.value.trim(),
    customer_comment: comment.value.trim() || null,
  }
  if (!items.value.length || total.value === null) return
  sending.value = true
  try {
    const body = JSON.stringify(details)
    if (attempt?.body !== body) attempt = { body, key: crypto.randomUUID() }
    const order = await placeOrder(details, attempt.key)
    if (!order)
      throw new CheckoutError(
        'Корзина больше недоступна. Проверьте её содержимое.',
      )
    emailProvided.value = Boolean(details.customer_email)
    receipt.value = order
    attempt = undefined
    name.value = phone.value = email.value = address.value = comment.value = ''
    await nextTick()
    confirmation.value?.querySelector('h1')?.focus()
  } catch (failure) {
    error.value =
      failure instanceof CheckoutError
        ? failure.message
        : 'Не удалось оформить заказ. Попробуйте ещё раз.'
    if (failure instanceof CheckoutError && failure.step)
      await goToStep(failure.step)
    await nextTick()
    notice.value?.focus()
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="container">
    <div v-if="receipt" ref="confirmation">
      <CheckoutConfirmation :order="receipt" :email-provided="emailProvided" />
    </div>
    <template v-else>
      <CheckoutHeading
        title="Оформление заказа"
        eyebrow="Почти готово"
        step="checkout"
      />
      <p
        v-if="!ready || (busy && !items.length)"
        class="checkout-state"
        role="status"
      >
        Загружаем корзину…
      </p>
      <section v-else-if="cartError" class="checkout-state">
        <p role="alert">{{ cartError }}</p>
        <UiButton :disabled="busy" @click="readCart">Повторить</UiButton>
      </section>
      <section v-else-if="!items.length" class="checkout-state">
        <p class="eyebrow">Пока ничего нет</p>
        <h2>Корзина пуста</h2>
        <p>Добавьте товары в корзину, чтобы оформить заказ.</p>
        <UiButton to="/cart" arrow>Вернуться в корзину</UiButton>
      </section>
      <CheckoutLayout v-else summary-first>
        <template #summary>
          <CheckoutSummary
            title="Ваш заказ"
            collapsible
            :items="items"
            :count="itemCount"
            :total="total"
          >
            <NuxtLink class="checkout-back" to="/cart"
              >← Изменить корзину</NuxtLink
            >
          </CheckoutSummary>
        </template>
        <form
          ref="form"
          class="checkout-form"
          :aria-busy="sending"
          @submit.prevent="advance"
        >
          <p
            v-if="error"
            ref="notice"
            class="checkout-error"
            role="alert"
            tabindex="-1"
          >
            {{ error }}
          </p>
          <CheckoutSection
            number="01"
            title="Контакты"
            :active="step === 1"
            :complete="step > 1"
            :busy="sending"
            @edit="goToStep(1)"
          >
            <template #review
              >{{ name }}<br />{{ formatPhone(phone)
              }}<template v-if="email"> · {{ email }}</template></template
            >
            <div class="checkout-fields">
              <UiTextField
                v-model="name"
                label="ФИО"
                autocomplete="name"
                pattern=".*\S.*"
                :maxlength="255"
                required
              />
              <div class="checkout-fields checkout-fields--pair">
                <UiTextField
                  v-model="phone"
                  label="Телефон"
                  type="tel"
                  autocomplete="tel"
                  required
                />
                <UiTextField
                  v-model="email"
                  label="Email (необязательно)"
                  type="email"
                  autocomplete="email"
                  :maxlength="255"
                />
              </div>
            </div>
            <div class="checkout-actions">
              <UiButton type="submit" :disabled="busy" arrow
                >Продолжить</UiButton
              >
            </div>
          </CheckoutSection>
          <CheckoutSection
            number="02"
            title="Доставка"
            :active="step === 2"
            :complete="step > 2"
            :busy="sending"
            @edit="goToStep(2)"
          >
            <template #review>{{ address }}</template>
            <div class="checkout-fields">
              <UiTextField
                v-model="address"
                label="Адрес доставки"
                autocomplete="street-address"
                pattern=".*\S.*"
                :maxlength="1000"
                required
              />
              <UiTextarea
                v-model="comment"
                label="Комментарий (необязательно)"
                :maxlength="2000"
              />
            </div>
            <div class="checkout-actions">
              <UiButton type="submit" :disabled="busy" arrow
                >Продолжить</UiButton
              >
              <button class="checkout-back" type="button" @click="goToStep(1)">
                Назад
              </button>
            </div>
          </CheckoutSection>
          <CheckoutSection
            number="03"
            title="Подтверждение"
            :active="step === 3"
            :complete="false"
            :busy="sending"
          >
            <p class="checkout-note">
              Менеджер подтвердит наличие товаров и согласует с вами доставку и
              оплату.
            </p>
            <p v-if="total === null" class="checkout-note">
              У некоторых товаров нужно уточнить цену. Проверьте корзину.
            </p>
            <div class="checkout-actions">
              <UiButton
                type="submit"
                :disabled="busy || sending || total === null"
                arrow
              >
                {{ sending ? 'Отправляем…' : 'Подтвердить заказ' }}
              </UiButton>
              <button
                class="checkout-back"
                type="button"
                :disabled="sending"
                @click="goToStep(2)"
              >
                Назад
              </button>
            </div>
            <p v-if="total !== null" class="checkout-note checkout-note--total">
              Итого за товары {{ formatMoney(total) }}
            </p>
          </CheckoutSection>
        </form>
      </CheckoutLayout>
    </template>
  </div>
</template>

<style scoped>
.checkout-state {
  max-width: 520px;
  margin: 0 auto;
  padding: 65px 0 var(--section-space);
  text-align: center;
}
.checkout-state h2 {
  font-size: 32px;
  font-weight: 500;
}
.checkout-state p {
  line-height: 1.7;
}
.checkout-form {
  border-bottom: 1px solid var(--color-line);
}
.checkout-fields {
  display: grid;
  gap: 18px;
}
.checkout-fields--pair {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.checkout-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px 24px;
  margin-top: 30px;
}
.checkout-back {
  border: 0;
  border-bottom: 1px solid currentColor;
  padding: 5px 0;
  background: transparent;
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.checkout-back:hover {
  color: var(--color-ink);
}
.checkout-note,
.checkout-error {
  font-size: 14px;
  line-height: 1.7;
}
.checkout-note {
  margin: 0;
  color: var(--color-muted);
}
.checkout-note--total {
  margin-top: 14px;
}
.checkout-error {
  margin: 0 0 24px;
  border-left: 2px solid var(--color-ink);
  padding-left: 12px;
}
@media (max-width: 700px) {
  .checkout-fields--pair {
    grid-template-columns: minmax(0, 1fr);
  }
  .checkout-actions .ui-button {
    max-width: 100%;
  }
}
</style>
