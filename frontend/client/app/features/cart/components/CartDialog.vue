<script setup lang="ts">
import UiDialog from '~/components/ui/UiDialog.vue'
import UiButton from '~/components/ui/UiButton.vue'
import CartLineItem from './CartLineItem.vue'
import { useCart } from '../composables/useCart'
import { formatMoney } from '~/utils/formatMoney'

const emit = defineEmits<{ close: [] }>()
const { items, busy, error, total, loadCart, updateItem, removeItem } =
  useCart()
const revision = ref(0)
onMounted(loadCart)
async function commitQuantity(itemId: number, quantity: number) {
  if (!(await updateItem(itemId, quantity))) revision.value++
}
</script>

<template>
  <UiDialog
    v-slot="{ close }"
    title="Корзина"
    :busy="busy"
    @close="emit('close')"
  >
    <div class="cart-content" :aria-busy="busy">
      <p v-if="busy" class="cart-loading" role="status">Обновляем корзину…</p>
      <div v-if="error" class="cart-error" role="alert">
        <p>{{ error }}</p>
        <UiButton :disabled="busy" @click="loadCart">Повторить</UiButton>
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
        <div class="cart-total">
          <span>Итого</span>
          <strong>{{
            total === null ? 'Уточняется' : formatMoney(total)
          }}</strong>
        </div>
      </div>
      <div v-else-if="!busy && !error" class="cart-empty">
        <h3>Корзина пока пуста</h3>
        <p>Добавьте нужные товары из каталога.</p>
      </div>
      <UiButton class="cart-continue" :disabled="busy" @click="close"
        >Продолжить выбор</UiButton
      >
    </div>
  </UiDialog>
</template>

<style scoped>
.cart-content {
  min-width: 0;
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
.cart-total {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 24px;
  margin: 28px 0;
  font-size: 18px;
  font-variant-numeric: tabular-nums;
}
.cart-total strong {
  font-weight: 500;
}
.cart-empty {
  padding: 25px 0 35px;
  text-align: center;
}
.cart-empty h3 {
  margin: 0 0 12px;
  font-size: 20px;
  font-weight: 500;
}
.cart-empty p {
  margin: 0;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.7;
}
.cart-continue {
  width: 100%;
}
</style>
