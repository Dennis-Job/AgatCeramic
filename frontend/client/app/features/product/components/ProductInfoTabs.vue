<script setup lang="ts">
import UiTabs from '~/components/ui/UiTabs.vue'
import type { ProductDetail } from '../types/product'
defineProps<{ product: ProductDetail; phone?: string; phoneHref?: string }>()
const selected = defineModel<string>({ default: 'specs' })
const tabs = [
  { id: 'specs', label: 'Характеристики' },
  { id: 'description', label: 'Описание' },
  { id: 'delivery', label: 'Доставка и заказ' },
]
</script>
<template>
  <section id="product-details" class="product-information">
    <div class="container">
      <UiTabs v-model="selected" label="Информация о товаре" :tabs="tabs">
        <template #specs>
          <dl v-if="product.attributes.length" class="product-specs">
            <div v-for="attribute in product.attributes" :key="attribute.id">
              <dt>{{ attribute.name }}</dt>
              <dd>
                {{ attribute.display_value
                }}<template v-if="attribute.unit">
                  {{ attribute.unit }}</template
                >
              </dd>
            </div>
          </dl>
          <p v-else class="empty-text">Характеристики пока не добавлены.</p>
        </template>
        <template #description
          ><div class="description">
            <h2>{{ product.name }}</h2>
            <p v-if="product.description">{{ product.description }}</p>
            <p v-else>Подробное описание пока не добавлено.</p>
          </div></template
        >
        <template #delivery
          ><div class="delivery">
            <div>
              <p class="eyebrow">Доставка</p>
              <h2>Согласуем детали заказа</h2>
              <p>
                Уточните возможность, стоимость и сроки доставки по вашему
                адресу у менеджера.
              </p>
              <a v-if="phone && phoneHref" :href="phoneHref">{{ phone }}</a>
            </div>
            <div>
              <p class="eyebrow">Оформление</p>
              <h2>Без онлайн-оплаты</h2>
              <p>
                Выберите товары и добавьте их в корзину. Условия получения и
                оплаты согласовываются при оформлении заказа.
              </p>
            </div>
          </div></template
        >
      </UiTabs>
    </div>
  </section>
</template>
<style scoped>
.product-information {
  background: var(--color-bg-soft);
  border-block: 1px solid var(--color-line);
  padding-block: 54px 64px;
  scroll-margin-top: 105px;
}
.product-specs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 70px;
  margin: 0;
}
.product-specs > div {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 13px 0;
  border-bottom: 1px solid var(--color-line);
  font-size: 13px;
}
dt {
  color: var(--color-muted);
}
dd {
  max-width: 60%;
  margin: 0;
  text-align: right;
  overflow-wrap: anywhere;
}
.description {
  max-width: 850px;
}
.description p {
  white-space: pre-line;
}
h2 {
  margin: 0 0 16px;
  font-size: 25px;
  font-weight: 500;
  line-height: 1.3;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}
p {
  margin: 0 0 16px;
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.9;
}
.delivery {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 70px;
}
.delivery a {
  font-size: 13px;
  border-bottom: 1px solid var(--color-line);
}
@media (max-width: 760px) {
  .product-information {
    padding-block: 36px 42px;
  }
  .product-specs {
    column-gap: 30px;
  }
  .product-specs > div {
    font-size: 12px;
  }
  .delivery {
    gap: 30px;
  }
}
@media (max-width: 520px) {
  .product-specs,
  .delivery {
    grid-template-columns: 1fr;
  }
}
</style>
