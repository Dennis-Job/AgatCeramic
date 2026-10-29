<script setup lang="ts">
import { ArrowRight, Check } from '@lucide/vue'
import UiButton from '~/components/ui/UiButton.vue'
import UiQuantityInput from '~/components/ui/UiQuantityInput.vue'
import { useCart } from '~/features/cart/composables/useCart'
import { formatMoney } from '~/utils/formatMoney'
import { estimateProductTotal, productUnitLabel } from '~/utils/productUnits'
import { productPath } from '../services/products'
import type { ProductDetail } from '../types/product'
const props = defineProps<{
  product: ProductDetail
  phone?: string
  phoneHref?: string
}>()
const emit = defineEmits<{ details: [tab: string] }>()
const quantity = ref(1)
const message = ref('')
const { addItem, busy, error } = useCart()
const { showSuccess } = useSuccessNotification()
const canBuy = computed(
  () =>
    props.product.is_on_sale &&
    props.product.in_stock &&
    props.product.price !== null,
)
const unit = computed(() => productUnitLabel(props.product.unit))
const estimate = computed(() =>
  estimateProductTotal(props.product.price, quantity.value),
)
const groupLabel = computed(
  () =>
    props.product.group?.axes.map((axis) => axis.name).join(' / ') ||
    'Варианты товара',
)
const brandLink = computed(() =>
  props.product.brand
    ? { path: '/catalog', query: { brand: props.product.brand.slug } }
    : '/catalog',
)
const collectionLink = computed(() =>
  props.product.collection
    ? {
        path: '/catalog',
        query: {
          brand: props.product.collection.brand_slug,
          collection: props.product.collection.name,
        },
      }
    : '/catalog',
)
const logoFailed = ref(false)
watch(
  () => props.product.id,
  () => {
    quantity.value = 1
    message.value = ''
    logoFailed.value = false
  },
)
function optionLabel(values: { slug: string; display_value: string }[]) {
  return (
    props.product.group?.axes
      .map(
        (axis) =>
          values.find((value) => value.slug === axis.slug)?.display_value ||
          '—',
      )
      .join(' · ') || ''
  )
}
async function add() {
  message.value = ''
  if (!canBuy.value || busy.value) return
  if (
    !Number.isInteger(quantity.value) ||
    quantity.value < 1 ||
    quantity.value > 9999
  ) {
    message.value = 'Укажите целое количество от 1 до 9999.'
    return
  }
  if (await addItem(props.product.id, quantity.value))
    showSuccess('Товар добавлен в корзину.')
  else
    message.value =
      error.value || 'Не удалось добавить товар. Попробуйте ещё раз.'
}
</script>
<template>
  <div class="product-purchase">
    <div v-if="product.brand" class="brand-row">
      <NuxtLink
        :to="brandLink"
        :aria-label="'Все товары ' + product.brand.name"
        class="brand-mark"
      >
        <img
          v-if="product.brand.logo_url && !logoFailed"
          :src="product.brand.logo_url"
          :alt="product.brand.logo_alt || product.brand.name"
          width="138"
          height="26"
          @error="logoFailed = true"
        />
        <span v-else>{{ product.brand.name }}</span>
      </NuxtLink>
      <NuxtLink :to="brandLink" class="product-text-link brand-link"
        >Все товары бренда <ArrowRight :size="15" aria-hidden="true"
      /></NuxtLink>
    </div>
    <p class="eyebrow">
      {{ product.category.name
      }}<template v-if="product.brand"> · {{ product.brand.name }}</template>
    </p>
    <h1>{{ product.name }}</h1>
    <div class="identifiers">
      <span v-if="product.article_number"
        >Артикул: {{ product.article_number }}</span
      ><span>Код товара: {{ product.sku }}</span>
    </div>
    <NuxtLink
      v-if="product.collection"
      :to="collectionLink"
      class="product-text-link collection-link"
      >Коллекция «{{ product.collection.name }}»
      <ArrowRight :size="15" aria-hidden="true"
    /></NuxtLink>
    <div class="price-box">
      <div class="price">
        <del
          v-if="
            product.old_price &&
            product.price &&
            Number(product.old_price) > Number(product.price)
          "
          >{{ formatMoney(product.old_price) }}</del
        ><span>{{
          product.price === null
            ? 'Цена по запросу'
            : formatMoney(product.price)
        }}</span
        ><small v-if="product.price !== null"> / {{ unit }}</small>
      </div>
      <span class="stock"
        ><Check v-if="canBuy" :size="14" aria-hidden="true" />{{
          !product.is_on_sale
            ? 'Продажа приостановлена'
            : product.in_stock
              ? 'В наличии'
              : 'Нет в наличии'
        }}</span
      >
    </div>
    <div
      v-if="product.group && product.group.products.length > 1"
      class="variants"
    >
      <p class="option-head">
        <span class="eyebrow">{{ groupLabel }}</span>
      </p>
      <div class="variant-options" :aria-label="groupLabel">
        <NuxtLink
          v-for="option in product.group.products"
          :key="option.id"
          :to="productPath(option.slug)"
          :aria-label="groupLabel + ': ' + optionLabel(option.axis_values)"
          :aria-current="option.id === product.id ? 'page' : undefined"
          class="variant"
        >
          <img
            v-if="option.image_url"
            :src="option.image_url"
            alt=""
            width="30"
            height="30"
            loading="lazy"
          /><span>{{ optionLabel(option.axis_values) || option.name }}</span
          ><Check
            v-if="option.id === product.id"
            :size="13"
            aria-hidden="true"
          />
        </NuxtLink>
      </div>
    </div>
    <form class="purchase-form" :aria-busy="busy" @submit.prevent="add">
      <div class="quantity-head">
        <span>Количество, {{ unit }}</span
        ><span>Целые единицы</span>
      </div>
      <div class="purchase-row">
        <UiQuantityInput
          v-model="quantity"
          :label="'Количество, ' + unit"
          :disabled="!canBuy || busy"
          @invalid="message = 'Укажите целое количество от 1 до 9999.'"
        /><UiButton type="submit" :disabled="!canBuy || busy"
          >{{ busy ? 'Добавляем…' : 'В корзину'
          }}<ArrowRight :size="16" aria-hidden="true"
        /></UiButton>
      </div>
      <p v-if="message" class="purchase-error" role="alert">{{ message }}</p>
      <div v-if="product.price !== null" class="amount-row">
        <span>Предварительная стоимость</span
        ><output>{{ estimate ? formatMoney(estimate) : '—' }}</output>
      </div>
    </form>
    <div v-if="phone && phoneHref" class="consultation">
      <span>Нужна помощь с выбором?</span
      ><a :href="phoneHref" class="product-text-link"
        >Связаться с нами <ArrowRight :size="15" aria-hidden="true"
      /></a>
    </div>
    <div class="quick-links">
      <a href="#product-details" @click="emit('details', 'specs')"
        >Все характеристики</a
      ><a href="#product-details" @click="emit('details', 'delivery')"
        >Доставка и заказ</a
      >
    </div>
  </div>
</template>
<style scoped>
.product-purchase {
  min-width: 0;
}
.brand-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  margin-bottom: 20px;
}
.brand-mark {
  display: block;
  flex: none;
  max-width: 45%;
  font-size: 14px;
}
.brand-mark img {
  display: block;
  width: 138px;
  max-width: 100%;
  height: auto;
  max-height: 50px;
  object-fit: contain;
}
.product-text-link {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  max-width: 100%;
  padding-bottom: 3px;
  border-bottom: 1px solid currentColor;
  font-size: 12px;
  line-height: 1.5;
}
.product-text-link svg {
  flex: none;
  transition: transform 0.3s;
}
.product-text-link:hover svg {
  transform: translateX(3px);
}
.brand-link {
  border: 0;
  color: var(--color-muted);
  font-size: 11px;
}
.brand-link:hover {
  color: var(--color-ink);
}
h1 {
  max-width: 560px;
  margin: 12px 0 15px;
  font-size: clamp(30px, 3.5vw, 44px);
  font-weight: 500;
  line-height: 1.12;
  letter-spacing: -0.025em;
  text-wrap: balance;
  overflow-wrap: anywhere;
}
.identifiers {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 22px;
  margin-top: 16px;
  color: var(--color-muted);
  font-size: 11px;
  overflow-wrap: anywhere;
}
.collection-link {
  margin-top: 18px;
}
.price-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px 20px;
  margin-top: 27px;
  padding-block: 23px 20px;
  border-block: 1px solid var(--color-line);
}
.price {
  font-size: 32px;
  font-weight: 500;
  line-height: 1.3;
  letter-spacing: -0.025em;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.price small {
  font-size: 13px;
  letter-spacing: 0;
  color: var(--color-muted);
  font-weight: 400;
  white-space: nowrap;
}
.price del {
  display: block;
  margin-bottom: 5px;
  font-size: 13px;
  color: var(--color-muted);
}
.stock {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: var(--color-muted);
}
.variants {
  padding-top: 23px;
}
.option-head {
  margin: 0 0 12px;
}
.variant-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.variant {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  border: 1px solid var(--color-line);
  padding: 7px 13px 7px 7px;
  background: var(--color-bg-soft);
  font-size: 12px;
  line-height: 1.5;
  transition: border-color 0.3s;
}
.variant img {
  width: 30px;
  height: 30px;
  object-fit: contain;
}
.variant:hover,
.variant[aria-current='page'] {
  border-color: var(--color-ink);
}
.variant span {
  overflow-wrap: anywhere;
}
.variant svg {
  flex: none;
}
.purchase-form {
  margin-top: 24px;
  padding-top: 23px;
  border-top: 1px solid var(--color-line);
}
.quantity-head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 10px;
  font-size: 12px;
}
.quantity-head > span:last-child {
  color: var(--color-muted);
}
.purchase-row {
  display: flex;
  align-items: stretch;
  gap: 12px;
}
.purchase-row > :last-child {
  min-width: 0;
  flex: 1;
}
.amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  padding-top: 12px;
  color: var(--color-muted);
  font-size: 11px;
}
.amount-row output {
  color: var(--color-ink);
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}
.purchase-error {
  margin: 12px 0 0;
  border-left: 2px solid var(--color-ink);
  padding-left: 12px;
  font-size: 13px;
  line-height: 1.7;
}
.consultation {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  margin-top: 23px;
  padding-top: 18px;
  border-top: 1px solid var(--color-line);
  font-size: 12px;
}
.consultation > span {
  color: var(--color-muted);
}
.quick-links {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 25px;
  margin-top: 21px;
  font-size: 11px;
}
.quick-links a {
  border-bottom: 1px solid var(--color-line);
  padding-bottom: 3px;
  color: var(--color-muted);
}
.quick-links a:hover {
  color: var(--color-ink);
}
@media (max-width: 1024px) {
  h1 {
    font-size: 34px;
  }
  .brand-row {
    gap: 12px;
  }
  .brand-link {
    font-size: 10px;
  }
  .purchase-row {
    flex-wrap: wrap;
  }
  .purchase-row > :first-child {
    width: 100%;
  }
  .purchase-row > :last-child {
    width: 100%;
    flex: auto;
  }
}
@media (max-width: 760px) {
  h1 {
    font-size: 34px;
  }
  .purchase-row {
    flex-wrap: nowrap;
  }
  .purchase-row > :first-child {
    width: 154px;
  }
  .purchase-row > :last-child {
    width: auto;
    flex: 1;
  }
}
@media (max-width: 520px) {
  h1 {
    font-size: 32px;
  }
  .price {
    font-size: 28px;
  }
  .purchase-row {
    flex-wrap: wrap;
  }
  .purchase-row > :first-child,
  .purchase-row > :last-child {
    width: 100%;
    flex: auto;
  }
  .consultation {
    font-size: 11px;
    gap: 12px;
  }
  .brand-row {
    gap: 16px;
  }
  .brand-link {
    gap: 5px;
  }
  .brand-mark img {
    width: 130px;
  }
}
</style>
