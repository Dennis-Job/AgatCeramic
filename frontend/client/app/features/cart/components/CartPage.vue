<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
import CheckoutHeading from '~/features/checkout/components/CheckoutHeading.vue'
import CheckoutLayout from '~/features/checkout/components/CheckoutLayout.vue'
import CheckoutSummary from '~/features/checkout/components/CheckoutSummary.vue'
import CartLineItem from './CartLineItem.vue'
import { useCart } from '../composables/useCart'

const {
  items,
  itemCount,
  busy,
  error,
  canRetry,
  total,
  loadCart,
  updateItem,
  removeItem,
} = useCart()
const loaded = ref(false)
const revision = ref(0)
const itemsHeading = useId()
const canCheckout = computed(() => !busy.value && total.value !== null)

onMounted(async () => {
  await loadCart()
  loaded.value = true
})

async function commitQuantity(itemId: number, quantity: number) {
  if (!(await updateItem(itemId, quantity))) revision.value++
}
</script>

<template>
  <div class="container">
    <CheckoutHeading title="Корзина" eyebrow="Ваш заказ" step="cart" />

    <p v-if="!loaded" class="cart-page__state" role="status">
      Загружаем корзину…
    </p>
    <template v-else>
      <div
        v-if="error"
        class="cart-page__notice"
        :class="{ 'cart-page__notice--standalone': !items.length }"
        role="alert"
      >
        <p>{{ error }}</p>
        <UiButton
          v-if="canRetry"
          size="compact"
          variant="outline"
          :disabled="busy"
          @click="loadCart"
        >
          Повторить
        </UiButton>
      </div>

      <CheckoutLayout v-if="items.length">
        <section
          class="cart-lines"
          :aria-labelledby="itemsHeading"
          :aria-busy="busy"
        >
          <h2 :id="itemsHeading" class="visually-hidden">Товары в корзине</h2>
          <div class="cart-lines__head eyebrow" aria-hidden="true">
            <span>Товар</span>
            <span>Количество</span>
            <span>Сумма</span>
          </div>
          <CartLineItem
            v-for="item in items"
            :key="item.id + '-' + revision"
            :item="item"
            :busy="busy"
            variant="page"
            @quantity="commitQuantity"
            @remove="removeItem"
          />
          <div class="cart-lines__foot">
            <NuxtLink to="/catalog" class="cart-page__back">
              <span aria-hidden="true">←</span> Продолжить покупки
            </NuxtLink>
          </div>
        </section>
        <template #summary>
          <CheckoutSummary :total="total" :count="itemCount">
            <UiButton
              :to="canCheckout ? '/checkout' : undefined"
              :disabled="!canCheckout"
              arrow
            >
              Оформить заказ
            </UiButton>
            <p v-if="total === null" class="cart-page__note">
              Чтобы оформить заказ, удалите позиции без цены.
            </p>
          </CheckoutSummary>
        </template>
      </CheckoutLayout>
      <section v-else-if="!error && !busy" class="cart-page__empty">
        <p class="eyebrow">Пока ничего нет</p>
        <h2>Корзина пуста</h2>
        <p>Выберите товары в каталоге и добавьте их в корзину.</p>
        <UiButton to="/catalog" arrow>Перейти в каталог</UiButton>
      </section>
      <p v-else-if="busy" class="cart-page__state" role="status">
        Загружаем корзину…
      </p>
    </template>
  </div>
</template>

<style scoped>
.cart-lines {
  --cart-page-columns: 104px minmax(0, 1fr) 150px 120px 44px;
  --cart-page-column-gap: 20px;
  min-width: 0;
}
.cart-lines__head {
  display: grid;
  grid-template-columns: var(--cart-page-columns);
  gap: var(--cart-page-column-gap);
  padding-bottom: 14px;
  border-bottom: 1px solid var(--color-ink);
}
.cart-lines__head span:first-child {
  grid-column: span 2;
}
.cart-lines__head span:last-child {
  text-align: right;
}
.cart-lines__foot {
  padding-top: 28px;
}
.cart-page__back {
  display: inline-flex;
  gap: 8px;
  border-bottom: 1px solid var(--color-ink);
  padding: 3px 0;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.cart-page__back:hover {
  color: var(--color-muted);
  border-color: var(--color-muted);
}
.cart-page__note {
  margin: 16px 0 0;
  font-size: 12px;
  line-height: 1.6;
  color: var(--color-muted);
}
.cart-page__notice {
  margin: 0 0 24px;
  border-left: 2px solid var(--color-ink);
  padding: 6px 16px;
  font-size: 14px;
  line-height: 1.6;
}
.cart-page__notice p {
  margin: 0;
}
.cart-page__notice button {
  margin-top: 12px;
}
.cart-page__notice--standalone {
  margin-bottom: var(--section-space);
}
.cart-page__empty,
.cart-page__state {
  max-width: 520px;
  margin: 0 auto;
  padding: clamp(30px, 6vw, 70px) 0 var(--section-space);
  text-align: center;
}
.cart-page__empty h2 {
  margin: 12px 0 16px;
  font-size: clamp(26px, 3vw, 38px);
  font-weight: 500;
  letter-spacing: -0.02em;
}
.cart-page__empty p:not(.eyebrow) {
  margin: 0 0 26px;
  color: var(--color-muted);
  line-height: 1.6;
}
.cart-page__state {
  color: var(--color-muted);
  font-size: 14px;
}
@media (max-width: 860px) {
  .cart-lines__head {
    display: none;
  }
  .cart-lines {
    border-top: 1px solid var(--color-ink);
  }
}
</style>
