<script setup lang="ts">
import { Trash2 } from '@lucide/vue'
import UiIconButton from '~/components/ui/UiIconButton.vue'
import UiQuantityInput from '~/components/ui/UiQuantityInput.vue'
import { formatMoney } from '~/utils/formatMoney'
import { productUnitLabel } from '~/utils/productUnits'
import type { CartItem } from '../types/cart'

const props = defineProps<{ item: CartItem; busy: boolean }>()
const emit = defineEmits<{
  quantity: [itemId: number, quantity: number]
  remove: [itemId: number]
}>()
const quantity = ref(props.item.quantity)
const quantityError = ref('')
watch(
  () => props.item.quantity,
  (value) => {
    quantity.value = value
  },
)
function invalidQuantity() {
  quantity.value = props.item.quantity
  quantityError.value =
    'Укажите целое количество от 1 до 9999. Сохранённое количество восстановлено.'
}
function commitQuantity(value: number) {
  quantityError.value = ''
  emit('quantity', props.item.id, value)
}
const failedImage = ref(false)
watch(
  () => props.item.product?.primary_image_url,
  () => {
    failedImage.value = false
  },
)
const name = computed(
  () => props.item.product?.name || 'Товар больше недоступен',
)
const productPath = computed(() =>
  props.item.product
    ? '/products/' + encodeURIComponent(props.item.product.slug)
    : null,
)
</script>

<template>
  <article class="cart-item">
    <div class="cart-item__image">
      <img
        v-if="item.product?.primary_image_url && !failedImage"
        :src="item.product.primary_image_url"
        :alt="name"
        width="72"
        height="72"
        @error="failedImage = true"
      />
      <span v-else>Нет фото</span>
    </div>
    <div class="cart-item__details">
      <h3>
        <NuxtLink v-if="productPath" :to="productPath">{{ name }}</NuxtLink>
        <template v-else>{{ name }}</template>
      </h3>
      <p class="cart-item__price">
        <template v-if="item.unit_price !== null && item.product">
          {{ formatMoney(item.unit_price) }} /
          {{ productUnitLabel(item.product.unit) }}
        </template>
        <template v-else>Стоимость уточняется</template>
      </p>
      <UiQuantityInput
        v-model="quantity"
        class="cart-item__quantity"
        compact
        :min="1"
        :max="9999"
        :disabled="busy || !item.product"
        :label="'Количество ' + name"
        @update:model-value="quantityError = ''"
        @commit="commitQuantity"
        @invalid="invalidQuantity"
      />
    </div>
    <div class="cart-item__summary">
      <p class="cart-item__total">
        {{
          item.line_total === null
            ? 'Стоимость уточняется'
            : formatMoney(item.line_total)
        }}
      </p>
      <UiIconButton
        plain
        :label="'Удалить ' + name"
        :disabled="busy"
        @click="$emit('remove', item.id)"
      >
        <Trash2 :size="16" :stroke-width="1.6" aria-hidden="true" />
      </UiIconButton>
    </div>
    <p v-if="quantityError" class="cart-item__error" role="alert">
      {{ quantityError }}
    </p>
  </article>
</template>

<style scoped>
.cart-item {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr) minmax(44px, max-content);
  align-items: center;
  gap: 16px;
}
.cart-item__image {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--color-beige);
}
.cart-item__image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.cart-item__image span {
  padding: 8px;
  color: var(--color-muted);
  font-size: 10px;
  text-align: center;
}
.cart-item__details {
  min-width: 0;
}
h3 {
  margin: 0 0 4px;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.5;
}
h3 a:hover {
  color: var(--color-muted);
}
.cart-item__price {
  margin: 0;
  color: var(--color-muted);
  font-size: 12px;
  line-height: 1.6;
}
.cart-item__quantity {
  margin-top: 10px;
}
.cart-item__summary {
  display: grid;
  justify-items: end;
  max-width: 108px;
  text-align: right;
}
.cart-item__total {
  margin: 0;
  font-size: 13px;
  font-weight: 500;
}
.cart-item__error {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 12px;
  line-height: 1.6;
}
@media (max-width: 400px) {
  .cart-item {
    grid-template-columns: 52px minmax(0, 1fr) minmax(44px, max-content);
    gap: 12px;
  }
  .cart-item__summary {
    max-width: 82px;
  }
}
</style>
