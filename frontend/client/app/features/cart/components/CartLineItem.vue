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
        width="80"
        height="80"
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
    </div>
    <UiIconButton
      :label="'Удалить ' + name"
      :disabled="busy"
      @click="$emit('remove', item.id)"
    >
      <Trash2 :size="17" :stroke-width="1.6" aria-hidden="true" />
    </UiIconButton>
    <div class="cart-item__purchase">
      <UiQuantityInput
        v-model="quantity"
        :min="1"
        :max="9999"
        :disabled="busy || !item.product"
        :label="'Количество ' + name"
        @update:model-value="quantityError = ''"
        @commit="commitQuantity"
        @invalid="invalidQuantity"
      />
      <p class="cart-item__total">
        {{
          item.line_total === null
            ? 'Стоимость уточняется'
            : formatMoney(item.line_total)
        }}
      </p>
      <p v-if="quantityError" class="cart-item__error" role="alert">
        {{ quantityError }}
      </p>
    </div>
  </article>
</template>

<style scoped>
.cart-item {
  display: grid;
  grid-template-columns: 76px minmax(0, 1fr) 44px;
  align-items: start;
  gap: 16px;
  padding: 24px 0;
  border-bottom: 1px solid var(--color-line);
}
.cart-item:first-child {
  padding-top: 0;
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
  object-fit: contain;
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
  margin: 0 0 7px;
  font-size: 14px;
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
.cart-item__purchase {
  grid-column: 2 / -1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.cart-item__total {
  margin: 0;
  font-size: 14px;
}
.cart-item__error {
  flex-basis: 100%;
  margin: 0;
  font-size: 12px;
  line-height: 1.6;
}
@media (max-width: 480px) {
  .cart-item {
    grid-template-columns: 52px minmax(0, 1fr) 44px;
    gap: 10px;
  }
  h3 {
    font-size: 13px;
  }
}
</style>
