<script setup lang="ts">
import UiDialog from '~/components/ui/UiDialog.vue'
import UiButton from '~/components/ui/UiButton.vue'
import CartLineItem from './CartLineItem.vue'
import { useCart } from '../composables/useCart'
import { formatMoney } from '~/utils/formatMoney'

const emit = defineEmits<{ close: [] }>()
const {
  items,
  busy,
  error,
  canRetry,
  total,
  loadCart,
  updateItem,
  removeItem,
} = useCart()
const revision = ref(0)
onMounted(loadCart)
async function commitQuantity(itemId: number, quantity: number) {
  if (!(await updateItem(itemId, quantity))) revision.value++
}

function openCartPage(close: () => void) {
  close()
  return navigateTo('/cart')
}
</script>

<template>
  <UiDialog title="Корзина" size="drawer" :busy="busy" @close="emit('close')">
    <template #title-extra>{{ ' (' + items.length + ')' }}</template>
    <template #default="{ close }">
      <div class="cart-content" :aria-busy="busy">
        <p
          v-if="busy"
          class="cart-loading"
          :class="{ 'visually-hidden': items.length }"
          role="status"
        >
          Обновляем корзину…
        </p>
        <div v-if="error" class="cart-error" role="alert">
          <p>{{ error }}</p>
          <UiButton v-if="canRetry" :disabled="busy" @click="loadCart">
            Повторить
          </UiButton>
        </div>
        <div v-if="items.length" class="cart-items">
          <CartLineItem
            v-for="item in items"
            :key="item.id + '-' + revision"
            :item="item"
            :busy="busy"
            @quantity="commitQuantity"
            @remove="removeItem"
          />
        </div>
        <p v-else-if="!busy && !error" class="cart-empty">
          Корзина пока пуста<br />Добавьте нужные товары из каталога.
        </p>
      </div>
      <div v-if="items.length" class="cart-footer">
        <div class="cart-total">
          <span>Итого</span>
          <strong>{{
            total === null ? 'Уточняется' : formatMoney(total)
          }}</strong>
        </div>
        <UiButton
          class="cart-continue"
          :disabled="busy"
          arrow
          @click="openCartPage(close)"
        >
          Оформить заказ
        </UiButton>
      </div>
    </template>
  </UiDialog>
</template>

<style scoped>
.cart-content {
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 24px var(--ui-drawer-gutter);
}
.cart-items {
  display: grid;
  gap: 24px;
}
.cart-loading {
  margin: 0 0 20px;
  color: var(--color-muted);
  font-size: 13px;
}
.cart-error {
  display: grid;
  gap: 16px;
  margin-bottom: 24px;
  border-left: 2px solid var(--color-ink);
  padding-left: 12px;
}
.cart-error p {
  margin: 0;
  font-size: 13px;
  line-height: 1.7;
}
.cart-footer {
  flex-shrink: 0;
  border-top: 1px solid var(--color-line);
  padding: 24px var(--ui-drawer-gutter);
}
.cart-total {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 24px;
  margin-bottom: 18px;
  font-size: 14px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}
.cart-total strong {
  font-weight: 500;
}
.cart-empty {
  margin: 60px 0 0;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.7;
  text-align: center;
}
.cart-continue {
  width: 100%;
}
</style>
