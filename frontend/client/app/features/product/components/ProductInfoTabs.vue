<script setup lang="ts">
import UiTabs from '~/components/ui/UiTabs.vue'
import type { ProductAttribute, ProductDetail } from '../types/product'
const props = defineProps<{
  product: ProductDetail
  phone?: string
  phoneHref?: string
}>()
const selected = defineModel<string>({ default: 'specs' })
const groups = computed(() => {
  const grouped = new Map<
    number | null,
    {
      id: number | null
      name: string
      order: number
      attributes: ProductAttribute[]
    }
  >()
  const hasGroups = props.product.attributes.some(
    (attribute) => attribute.group,
  )
  for (const attribute of props.product.attributes) {
    const id = attribute.group?.id ?? null
    let group = grouped.get(id)
    if (!group) {
      group = {
        id,
        name:
          attribute.group?.name ?? (hasGroups ? 'Прочие характеристики' : ''),
        order: attribute.group?.sort_order ?? Number.MAX_SAFE_INTEGER,
        attributes: [],
      }
      grouped.set(id, group)
    }
    group.attributes.push(attribute)
  }
  return [...grouped.values()].sort((a, b) => a.order - b.order)
})
const specsId = useId()
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
          <div
            v-if="groups.length"
            class="spec-groups"
            :class="{
              'spec-groups--plain': groups.length === 1 && !groups[0]?.name,
            }"
          >
            <div
              v-for="group in groups"
              :key="group.id ?? 'ungrouped'"
              class="spec-group"
              :role="group.name ? 'group' : undefined"
              :aria-labelledby="
                group.name
                  ? `${specsId}-group-${group.id ?? 'other'}`
                  : undefined
              "
            >
              <h2
                v-if="group.name"
                :id="`${specsId}-group-${group.id ?? 'other'}`"
                class="spec-group-title eyebrow"
              >
                {{ group.name }}
              </h2>
              <dl class="product-specs">
                <div v-for="attribute in group.attributes" :key="attribute.id">
                  <dt>{{ attribute.name }}</dt>
                  <dd>
                    {{ attribute.display_value
                    }}{{ attribute.unit ? ` ${attribute.unit}` : '' }}
                  </dd>
                </div>
              </dl>
            </div>
          </div>
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
.spec-groups {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 36px 70px;
  align-items: start;
}
.spec-group {
  min-width: 0;
}
.spec-group-title {
  margin: 0 0 14px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--color-line-strong);
  font-size: 11px;
  font-weight: 500;
  line-height: 1.5;
  letter-spacing: 0.22em;
}
.product-specs {
  margin: 0;
}
.spec-groups--plain {
  display: block;
}
.spec-groups--plain .product-specs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 70px;
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
  min-width: 0;
  color: var(--color-muted);
  overflow-wrap: anywhere;
}
dd {
  min-width: 0;
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
  .spec-groups,
  .spec-groups--plain .product-specs {
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
  .spec-groups,
  .spec-groups--plain .product-specs,
  .delivery {
    grid-template-columns: 1fr;
  }
}
</style>
