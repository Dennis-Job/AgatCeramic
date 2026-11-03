<script setup lang="ts">
import { Check } from '@lucide/vue'
import UiButton from '~/components/ui/UiButton.vue'
import { formatMoney } from '~/utils/formatMoney'
import type { OrderReceipt } from '../services/orders'

defineProps<{ order: OrderReceipt; emailProvided: boolean }>()
</script>

<template>
  <section
    class="checkout-confirmation"
    aria-labelledby="checkout-confirmation-title"
  >
    <span class="checkout-confirmation__icon"
      ><Check :size="26" aria-hidden="true"
    /></span>
    <p class="eyebrow">Заказ № {{ order.order_number }}</p>
    <h1 id="checkout-confirmation-title" tabindex="-1">Заказ принят</h1>
    <p class="checkout-confirmation__lead">
      Менеджер свяжется с вами, чтобы подтвердить наличие и согласовать
      доставку.
      <template v-if="emailProvided"
        >Подтверждение будет отправлено на указанный email.</template
      >
    </p>
    <div class="checkout-confirmation__receipt">
      <h2 class="eyebrow">Состав заказа</h2>
      <ul>
        <li v-for="(item, index) in order.items" :key="index">
          <div>
            {{ item.product_name }} <small>× {{ item.quantity }}</small>
          </div>
          <strong>{{ formatMoney(item.line_total) }}</strong>
        </li>
      </ul>
      <div class="checkout-confirmation__total">
        <span>Итого за товары</span
        ><strong>{{ formatMoney(order.total_amount) }}</strong>
      </div>
    </div>
    <UiButton to="/catalog" arrow>Вернуться в каталог</UiButton>
  </section>
</template>

<style scoped>
.checkout-confirmation {
  max-width: 920px;
  margin-inline: auto;
  padding: 56px 0 var(--section-space);
  overflow-wrap: anywhere;
}
.checkout-confirmation__icon {
  display: grid;
  width: 64px;
  height: 64px;
  margin-bottom: 30px;
  place-items: center;
  border: 1px solid var(--color-ink);
  border-radius: 50%;
}
h1 {
  margin: 16px 0 18px;
  font-size: clamp(34px, 4.4vw, 58px);
  line-height: 1.04;
  font-weight: 500;
  letter-spacing: -0.02em;
}
.checkout-confirmation__lead {
  max-width: 560px;
  color: var(--color-muted);
  line-height: 1.8;
}
.checkout-confirmation__receipt {
  margin: 44px 0;
  border: 1px solid var(--color-line);
  padding: 32px;
  background: var(--color-bg-soft);
}
h2 {
  margin: 0 0 18px;
}
ul {
  display: grid;
  gap: 16px;
  margin: 0;
  padding: 0;
  list-style: none;
}
li,
.checkout-confirmation__total {
  display: flex;
  justify-content: space-between;
  gap: 12px 24px;
}
li {
  font-size: 14px;
}
small {
  color: var(--color-muted);
}
strong {
  text-align: right;
  font-weight: 500;
}
.checkout-confirmation__total {
  margin-top: 22px;
  border-top: 1px solid var(--color-ink);
  padding-top: 20px;
  font-size: 18px;
}
@media (max-width: 700px) {
  .checkout-confirmation__receipt {
    padding: 22px 18px;
  }
  li,
  .checkout-confirmation__total {
    flex-wrap: wrap;
  }
}
</style>
