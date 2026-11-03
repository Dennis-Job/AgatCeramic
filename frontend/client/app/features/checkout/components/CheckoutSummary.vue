<script setup lang="ts">
import { Image } from '@lucide/vue'
import type { CartItem } from '~/features/cart/types/cart'
import { formatMoney } from '~/utils/formatMoney'
import { productUnitLabel } from '~/utils/productUnits'

withDefaults(
  defineProps<{
    title?: string
    items?: CartItem[]
    total: string | null
    count?: number
    collapsible?: boolean
  }>(),
  { title: 'Итого' },
)
const heading = useId()
const content = useId()
const expanded = ref(false)
const failed = ref(new Set<number>())
</script>

<template>
  <aside
    class="checkout-summary"
    :class="{ 'checkout-summary--collapsible': collapsible }"
    :aria-labelledby="heading"
  >
    <div class="checkout-summary__heading">
      <h2 :id="heading">
        {{ title }}
        <span
          v-if="collapsible && total !== null"
          class="checkout-summary__compact-total"
        >
          · {{ formatMoney(total) }}</span
        >
      </h2>
      <button
        v-if="collapsible"
        class="checkout-summary__toggle"
        type="button"
        :aria-expanded="expanded"
        :aria-controls="content"
        @click="expanded = !expanded"
      >
        {{ expanded ? 'Скрыть' : 'Показать' }}
      </button>
    </div>
    <div
      :id="content"
      :class="{ 'checkout-summary__body--collapsed': collapsible && !expanded }"
    >
      <ul v-if="items?.length" class="checkout-summary__items">
        <li v-for="item in items" :key="item.id">
          <div class="checkout-summary__photo">
            <img
              v-if="item.product?.primary_image_url && !failed.has(item.id)"
              :src="item.product.primary_image_url"
              width="52"
              height="52"
              alt=""
              @error="failed.add(item.id)"
            />
            <Image v-else :size="20" aria-hidden="true" />
          </div>
          <div>
            <strong>{{
              item.product?.name || 'Товар больше недоступен'
            }}</strong>
            <small
              >{{ item.quantity }}
              {{ item.product && productUnitLabel(item.product.unit) }}</small
            >
          </div>
          <span>{{
            item.line_total === null
              ? 'Уточняется'
              : formatMoney(item.line_total)
          }}</span>
        </li>
      </ul>
      <div v-if="count !== undefined" class="checkout-summary__line">
        <span>Товары ({{ count }})</span>
        <span>{{ total === null ? 'Уточняется' : formatMoney(total) }}</span>
      </div>
      <div class="checkout-summary__line">
        <span>Доставка</span><span>Уточнит менеджер</span>
      </div>
      <div class="checkout-summary__line checkout-summary__total">
        <span>Итого за товары</span>
        <strong>{{
          total === null ? 'Уточняется' : formatMoney(total)
        }}</strong>
      </div>
      <div v-if="$slots.default" class="checkout-summary__action"><slot /></div>
    </div>
  </aside>
</template>

<style scoped>
.checkout-summary {
  min-width: 0;
  border: 1px solid var(--color-line);
  padding: 32px;
  background: var(--color-bg-soft);
  overflow-wrap: anywhere;
}
h2 {
  margin: 0 0 22px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}
.checkout-summary__toggle,
.checkout-summary__compact-total {
  display: none;
}
@media (max-width: 1100px) {
  .checkout-summary--collapsible .checkout-summary__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .checkout-summary--collapsible h2 {
    margin: 0;
  }
  .checkout-summary--collapsible
    .checkout-summary__heading
    + div:not(.checkout-summary__body--collapsed) {
    margin-top: 18px;
  }
  .checkout-summary__compact-total {
    display: inline;
  }
  .checkout-summary__toggle {
    display: block;
    flex-shrink: 0;
    min-height: 44px;
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--color-ink);
    font-size: 11px;
    letter-spacing: 0.1em;
    text-decoration: underline;
    text-underline-offset: 3px;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .checkout-summary__body--collapsed {
    display: none;
  }
}
.checkout-summary__items {
  display: grid;
  gap: 16px;
  margin: 0 0 6px;
  border-bottom: 1px solid var(--color-line);
  padding: 0 0 22px;
  list-style: none;
}
li {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr) minmax(0, max-content);
  align-items: center;
  gap: 14px;
  font-size: 13px;
}
.checkout-summary__photo {
  display: grid;
  grid-template: minmax(0, 1fr) / minmax(0, 1fr);
  width: 52px;
  height: 52px;
  place-items: center;
  background: var(--color-beige);
}
img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
strong {
  font-weight: 500;
}
small {
  display: block;
  margin-top: 3px;
  color: var(--color-muted);
}
li > span {
  max-width: 110px;
  text-align: right;
}
.checkout-summary__line {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  font-size: 13px;
}
.checkout-summary__line span:first-child {
  color: var(--color-muted);
}
.checkout-summary__line > :last-child {
  text-align: right;
}
.checkout-summary__total {
  flex-wrap: wrap;
  margin-top: 10px;
  border-top: 1px solid var(--color-ink);
  padding: 20px 0 0;
  font-size: 18px;
}
.checkout-summary__total span:first-child {
  flex: 1 1 min-content;
  color: var(--color-ink);
}
.checkout-summary__total strong {
  max-width: 100%;
  margin-inline-start: auto;
}
.checkout-summary__action {
  margin-top: 22px;
}
.checkout-summary__action :deep(.ui-button) {
  width: 100%;
}
@media (max-width: 700px) {
  .checkout-summary {
    padding: 22px 20px;
  }
  li {
    gap: 10px;
  }
  li > span {
    max-width: 74px;
  }
}
</style>
