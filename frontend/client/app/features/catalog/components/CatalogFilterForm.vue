<script setup lang="ts">
import UiButton from '~/components/ui/UiButton.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import UiCheckbox from '~/components/ui/UiCheckbox.vue'
import UiTextField from '~/components/ui/UiTextField.vue'
import UiRange from '~/components/ui/UiRange.vue'
import CatalogAttributeFilter from './CatalogAttributeFilter.vue'
import PageState from '~/components/shared/PageState.vue'
import type { CatalogDraft, CatalogFilters } from '../types/catalog'

const props = defineProps<{
  filters: CatalogFilters
  collections: CatalogFilters['collections']
  busy?: boolean
  pending?: boolean
  error?: string
  hideBrand?: boolean
  hideCollection?: boolean
  scoped?: boolean
  autoApply?: boolean
}>()
const model = defineModel<CatalogDraft>({ required: true })
const emit = defineEmits<{
  apply: [automatic?: boolean]
  reset: []
  retry: []
}>()
const form = ref<HTMLFormElement | null>(null)
const route = useRoute()
const priceBounds = computed(() => {
  const range = props.filters.price_range
  if (!range) return null
  const min = Math.round(Number(range.min) * 100)
  const max = Math.round(Number(range.max) * 100)
  if (
    !Number.isSafeInteger(min) ||
    !Number.isSafeInteger(max) ||
    min < 0 ||
    max < min
  )
    return null
  return { min, max }
})
const sliderFrom = computed({
  get: () =>
    Math.min(
      sliderTo.value,
      Math.max(
        priceBounds.value?.min ?? 0,
        Math.round(
          Number(
            model.value.price_from || props.filters.price_range?.min || 0,
          ) * 100,
        ) || 0,
      ),
    ),
  set: (value: number) => {
    model.value.price_from = (value / 100).toFixed(2)
  },
})
const sliderTo = computed({
  get: () =>
    Math.max(
      priceBounds.value?.min ?? 0,
      Math.min(
        priceBounds.value?.max ?? 0,
        Math.round(
          Number(model.value.price_to || props.filters.price_range?.max || 0) *
            100,
        ) || 0,
      ),
    ),
  set: (value: number) => {
    model.value.price_to = (value / 100).toFixed(2)
  },
})
function priceHint(value?: string) {
  return value
    ? Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
    : undefined
}
function sliderPrice(value: number) {
  return (value / 100).toLocaleString('ru-RU', {
    style: 'currency',
    currency: 'RUB',
  })
}
let timer: ReturnType<typeof setTimeout> | undefined
let media: MediaQueryList | undefined
function cancel() {
  clearTimeout(timer)
  timer = undefined
}
function validPrices() {
  const { price_from: from, price_to: to } = model.value
  if ([from, to].some((value) => value && !/^\d+(?:\.\d{1,2})?$/.test(value)))
    return false
  const cents = (value: string) => {
    const [integer = '', decimal = ''] = value.split('.')
    return BigInt(integer + decimal.padEnd(2, '0'))
  }
  return !from || !to || cents(from) <= cents(to)
}
function queueApply(delay = 0) {
  if (!props.autoApply) return
  cancel()
  timer = setTimeout(() => {
    timer = undefined
    if (
      window.matchMedia('(min-width: 1024px)').matches &&
      form.value?.checkValidity() &&
      validPrices()
    )
      emit('apply', true)
  }, delay)
}
function submit() {
  cancel()
  const automatic =
    !!props.autoApply && window.matchMedia('(min-width: 1024px)').matches
  if (automatic && (!form.value?.checkValidity() || !validPrices())) return
  emit('apply', automatic)
}
function reset() {
  cancel()
  emit('reset')
}
watch(() => route.fullPath, cancel)
onMounted(() => {
  media = window.matchMedia('(min-width: 1024px)')
  media.addEventListener('change', cancel)
})
onBeforeUnmount(() => {
  cancel()
  media?.removeEventListener('change', cancel)
})
</script>

<template>
  <form
    ref="form"
    class="catalog-filter-form"
    :class="{ 'catalog-filter-form--automatic': autoApply }"
    aria-label="Подбор товаров"
    @submit.prevent="submit()"
  >
    <div v-if="autoApply" class="catalog-filter-form__heading">
      <h2>Фильтры</h2>
      <button
        class="catalog-filter-form__reset"
        type="button"
        :aria-label="scoped ? 'Сбросить фильтры' : 'Сбросить всё'"
        @click="reset()"
      >
        Сбросить
      </button>
    </div>
    <PageState
      v-if="error"
      title="Не удалось загрузить фильтры"
      :message="error"
      error
      @retry="$emit('retry')"
    />
    <p v-else-if="pending" role="status">Загружаем фильтры…</p>
    <UiSelect
      v-if="!hideBrand"
      v-model="model.brand"
      class="catalog-filter-form__brand"
      label="Бренд"
      size="compact"
      :disabled="!autoApply && (busy || pending || !!error)"
      :options="[
        { value: '', label: 'Все бренды' },
        ...filters.brands.map((item) => ({
          value: item.slug,
          label: item.name,
        })),
        ...(model.brand &&
        !filters.brands.some((item) => item.slug === model.brand)
          ? [{ value: model.brand, label: model.brand }]
          : []),
      ]"
      @update:model-value="queueApply()"
    />
    <fieldset
      class="catalog-filter-form__fields"
      :disabled="(!autoApply && busy) || pending || !!error"
    >
      <UiSelect
        v-if="!hideCollection"
        v-model="model.collection"
        @update:model-value="queueApply()"
        label="Коллекция"
        size="compact"
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
          <UiTextField
            v-model="model.price_from"
            @update:model-value="queueApply(350)"
            label="Цена от"
            prefix="от"
            :placeholder="priceHint(filters.price_range?.min)"
            size="compact"
            money
          />
          <UiTextField
            v-model="model.price_to"
            @update:model-value="queueApply(350)"
            label="Цена до"
            prefix="до"
            :placeholder="priceHint(filters.price_range?.max)"
            size="compact"
            money
          />
        </div>
        <UiRange
          v-if="priceBounds && priceBounds.max > priceBounds.min"
          v-model:from="sliderFrom"
          v-model:to="sliderTo"
          :min="priceBounds.min"
          :max="priceBounds.max"
          label-from="Минимальная цена"
          label-to="Максимальная цена"
          :format-value="sliderPrice"
          @change="queueApply(350)"
        />
      </fieldset>
      <div class="catalog-filter-form__flags">
        <UiCheckbox
          v-model="model.in_stock"
          label="В наличии"
          variant="switch"
          size="compact"
          @update:model-value="queueApply()"
        />
        <UiCheckbox
          v-model="model.is_on_sale"
          @update:model-value="queueApply()"
          label="Распродажа"
          variant="switch"
          size="compact"
        />
      </div>
      <CatalogAttributeFilter
        v-for="attribute in filters.attributes"
        :key="attribute.id"
        v-model="model.attributes[String(attribute.id)]"
        :attribute="attribute"
        @change="queueApply($event ? 350 : 0)"
      />
      <div class="catalog-filter-form__actions">
        <UiButton
          class="catalog-filter-form__submit"
          type="submit"
          size="compact"
          :disabled="busy"
          >Применить</UiButton
        >
        <UiButton
          variant="outline"
          size="compact"
          :disabled="!autoApply && busy"
          @click="reset()"
          >{{ scoped ? 'Сбросить фильтры' : 'Сбросить всё' }}</UiButton
        >
      </div>
    </fieldset>
  </form>
</template>

<style scoped>
.catalog-filter-form {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.catalog-filter-form__fields {
  display: grid;
  gap: 16px;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.catalog-filter-form__heading {
  display: none;
}
.catalog-filter-form__heading h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}
.catalog-filter-form__reset {
  min-height: 28px;
  border: 0;
  padding: 4px 0;
  background: transparent;
  font-size: 13px;
  color: var(--color-ink-soft);
  text-decoration: underline;
  text-underline-offset: 3px;
}
.catalog-filter-form__reset:hover {
  color: var(--color-ink);
}
.catalog-filter-form__price {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.catalog-filter-form__flags {
  display: grid;
  gap: 4px;
}
legend {
  margin-bottom: 8px;
  padding: 0;
  font-size: 13px;
}
.catalog-filter-form__range {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.catalog-filter-form__actions {
  display: grid;
  gap: 8px;
  border-top: 1px solid var(--color-line);
  padding-top: 16px;
}
@media (min-width: 1024px) {
  .catalog-filter-form--automatic .catalog-filter-form__heading {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid var(--color-line-strong);
    padding-bottom: 12px;
  }
  .catalog-filter-form--automatic .catalog-filter-form__brand,
  .catalog-filter-form--automatic
    .catalog-filter-form__fields
    > :deep(.ui-select),
  .catalog-filter-form--automatic .catalog-filter-form__price {
    border-bottom: 1px solid var(--color-line-strong);
    padding-bottom: 16px;
  }
  .catalog-filter-form--automatic .catalog-filter-form__actions {
    display: none;
  }
  .catalog-filter-form--automatic .catalog-filter-form__submit {
    display: none;
  }
}
@media (pointer: coarse) {
  .catalog-filter-form__reset {
    min-height: 44px;
  }
}
</style>
