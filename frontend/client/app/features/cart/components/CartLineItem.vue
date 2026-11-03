<script setup lang="ts">
import { Trash2, X } from '@lucide/vue'
import UiIconButton from '~/components/ui/UiIconButton.vue'
import UiQuantityInput from '~/components/ui/UiQuantityInput.vue'
import { formatMoney } from '~/utils/formatMoney'
import { productUnitLabel } from '~/utils/productUnits'
import type { CartItem } from '../types/cart'

const props = withDefaults(
  defineProps<{ item: CartItem; busy: boolean; variant?: 'drawer' | 'page' }>(),
  { variant: 'drawer' },
)
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
const isPage = computed(() => props.variant === 'page')
const imageSize = computed(() => (isPage.value ? 104 : 72))
</script>

<template>
  <article class="cart-item" :class="{ 'cart-item--page': isPage }">
    <div class="cart-item__image">
      <img
        v-if="item.product?.primary_image_url && !failedImage"
        :src="item.product.primary_image_url"
        :alt="name"
        :width="imageSize"
        :height="imageSize"
        @error="failedImage = true"
      />
      <span v-else>Нет фото</span>
    </div>
    <div class="cart-item__details">
      <div class="cart-item__product">
        <p v-if="isPage && item.product?.sku" class="cart-item__code eyebrow">
          Код товара {{ item.product.sku }}
        </p>
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
      <div class="cart-item__quantity">
        <UiQuantityInput
          v-model="quantity"
          class="cart-item__quantity-control"
          :compact="!isPage"
          :min="1"
          :max="9999"
          :disabled="busy || !item.product"
          :label="'Количество ' + name"
          @update:model-value="quantityError = ''"
          @commit="commitQuantity"
          @invalid="invalidQuantity"
        />
        <small v-if="isPage && item.product">
          {{ productUnitLabel(item.product.unit) }}
        </small>
      </div>
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
        class="cart-item__remove"
        :plain="!isPage"
        :label="'Удалить ' + name"
        :disabled="busy"
        @click="$emit('remove', item.id)"
      >
        <X v-if="isPage" :size="16" :stroke-width="1.6" aria-hidden="true" />
        <Trash2 v-else :size="16" :stroke-width="1.6" aria-hidden="true" />
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
.cart-item--page {
  grid-template-columns: var(--cart-page-columns);
  gap: var(--cart-page-column-gap);
  padding: 26px 0;
  border-bottom: 1px solid var(--color-line);
}
.cart-item--page .cart-item__details,
.cart-item--page .cart-item__summary {
  display: contents;
}
.cart-item--page .cart-item__product {
  min-width: 0;
  overflow-wrap: anywhere;
}
.cart-item__code {
  margin: 0 0 8px;
  font-size: 10px;
}
.cart-item--page h3 {
  margin-bottom: 6px;
  font-size: 16px;
}
.cart-item--page .cart-item__quantity {
  min-width: 0;
  margin-top: 0;
}
.cart-item--page .cart-item__quantity-control {
  max-width: 100%;
}
.cart-item--page small {
  display: block;
  margin-top: 8px;
  color: var(--color-muted);
  font-size: 11px;
}
.cart-item--page .cart-item__total {
  justify-self: end;
  text-align: right;
  overflow-wrap: anywhere;
  font-size: 15px;
  font-variant-numeric: tabular-nums;
}
.cart-item--page .cart-item__image span {
  color: var(--color-ink-soft);
}
@media (max-width: 860px) {
  .cart-item--page {
    grid-template-columns: 84px minmax(0, 1fr) minmax(72px, max-content);
    grid-template-areas:
      'image product remove'
      'image quantity total';
    align-items: start;
    gap: 14px 16px;
  }
  .cart-item--page .cart-item__image {
    grid-area: image;
  }
  .cart-item--page .cart-item__product {
    grid-area: product;
  }
  .cart-item--page .cart-item__quantity {
    grid-area: quantity;
  }
  .cart-item--page .cart-item__total {
    grid-area: total;
    align-self: start;
  }
  .cart-item--page .cart-item__remove {
    grid-area: remove;
    justify-self: end;
  }
  .cart-item--page .cart-item__error {
    grid-row: 3;
  }
}
@media (max-width: 480px) {
  .cart-item--page {
    grid-template-columns: 64px minmax(0, 1fr) minmax(80px, 30%);
    grid-template-areas:
      'image product remove'
      'quantity quantity total';
    gap: 12px;
  }
  .cart-item--page .cart-item__total {
    align-self: center;
  }
}
@media (max-width: 400px) {
  .cart-item:not(.cart-item--page) {
    grid-template-columns: 52px minmax(0, 1fr) minmax(44px, max-content);
    gap: 12px;
  }
  .cart-item:not(.cart-item--page) .cart-item__summary {
    max-width: 82px;
  }
}
</style>
