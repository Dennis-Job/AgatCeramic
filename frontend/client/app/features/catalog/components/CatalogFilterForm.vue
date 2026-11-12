<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import UiCheckbox from '~/components/ui/UiCheckbox.vue'
import UiTextField from '~/components/ui/UiTextField.vue'
import CatalogAttributeFilter from './CatalogAttributeFilter.vue'
import type { CatalogDraft, CatalogFilters } from '../types/catalog'

defineProps<{
  filters: CatalogFilters
  collections: CatalogFilters['collections']
  busy?: boolean
}>()
const model = defineModel<CatalogDraft>({ required: true })
defineEmits<{ apply: []; reset: [] }>()
</script>

<template>
  <form
    class="catalog-filter-form"
    aria-label="Подбор товаров"
    @submit.prevent="$emit('apply')"
  >
    <fieldset class="catalog-filter-form__fields" :disabled="busy">
      <UiSelect
        v-model="model.brand"
        label="Бренд"
        :options="[
          { value: '', label: 'Все бренды' },
          ...filters.brands.map((item) => ({
            value: item.slug,
            label: item.name,
          })),
        ]"
      />
      <UiSelect
        v-model="model.collection"
        label="Коллекция"
        :disabled="!model.brand"
        :options="[
          {
            value: '',
            label: model.brand ? 'Все коллекции' : 'Сначала выберите бренд',
          },
          ...collections.map((item) => ({
            value: item.name,
            label: item.name,
          })),
          ...(model.collection &&
          !collections.some((item) => item.name === model.collection)
            ? [{ value: model.collection, label: model.collection }]
            : []),
        ]"
      />
      <fieldset class="catalog-filter-form__price">
        <legend>Цена за единицу продажи, ₽</legend>
        <div class="catalog-filter-form__range">
          <UiTextField v-model="model.price_from" label="Цена от" money />
          <UiTextField v-model="model.price_to" label="Цена до" money />
        </div>
      </fieldset>
      <UiCheckbox v-model="model.in_stock" label="В наличии" />
      <CatalogAttributeFilter
        v-for="attribute in filters.attributes"
        :key="attribute.id"
        v-model="model.attributes[String(attribute.id)]"
        :attribute="attribute"
      />
      <div class="catalog-filter-form__actions">
        <UiButton type="submit" :disabled="busy">Применить</UiButton>
        <UiButton variant="outline" :disabled="busy" @click="$emit('reset')"
          >Сбросить всё</UiButton
        >
      </div>
    </fieldset>
  </form>
</template>

<style scoped>
.catalog-filter-form {
  display: grid;
  gap: 24px;
  min-width: 0;
}
.catalog-filter-form__fields {
  display: grid;
  gap: 24px;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.catalog-filter-form__price {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
legend {
  margin-bottom: 12px;
  padding: 0;
  font-size: 13px;
}
.catalog-filter-form__range {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.catalog-filter-form__actions {
  display: grid;
  gap: 12px;
  border-top: 1px solid var(--color-line);
  padding-top: 24px;
}
</style>
