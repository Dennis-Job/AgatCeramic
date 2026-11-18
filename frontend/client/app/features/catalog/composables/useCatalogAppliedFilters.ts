import type { ComputedRef, Ref } from 'vue'
import type { CatalogQuery, StandaloneCatalog } from '../types/catalog'
import { formatMoney } from '~/utils/formatMoney'

export function useCatalogAppliedFilters(
  catalog: Ref<StandaloneCatalog | null | undefined>,
  query: ComputedRef<CatalogQuery>,
) {
  const filters = computed(
    () =>
      catalog.value?.filters || { brands: [], collections: [], attributes: [] },
  )
  const chips = computed(() =>
    Object.entries(query.value).flatMap(([key, value]) => {
      if (key === 'page' || key === 'sort') return []
      if (key === 'is_on_sale' && value !== '1') return []
      if (key === 'attributes') {
        try {
          const attributes = JSON.parse(value) as Record<string, unknown>
          return Object.entries(attributes).map(([id, raw]) => {
            const attribute = filters.value.attributes.find(
              (attribute) => String(attribute.id) === id,
            )
            const filter =
              raw && typeof raw === 'object' && !Array.isArray(raw)
                ? (raw as Record<string, unknown>)
                : {}
            const values = Array.isArray(filter.values)
              ? filter.values
                  .filter((value) => typeof value === 'string')
                  .map(
                    (value) =>
                      attribute?.options.find(
                        (option) => option.value === value,
                      )?.label ||
                      (attribute?.type === 'boolean'
                        ? value === 'true'
                          ? 'Да'
                          : 'Нет'
                        : value),
                  )
                  .join(', ')
              : ''
            const bounds = [
              typeof filter.min === 'string' && filter.min
                ? 'от ' + filter.min
                : '',
              typeof filter.max === 'string' && filter.max
                ? 'до ' + filter.max
                : '',
            ]
              .filter(Boolean)
              .join(' ')
            const detail =
              values ||
              (bounds
                ? bounds + (attribute?.unit ? ' ' + attribute.unit : '')
                : '') ||
              (typeof filter.text === 'string' ? filter.text : '')
            return {
              key,
              id,
              label:
                (attribute?.name || 'Характеристика ' + id) +
                (detail ? ': ' + detail : ''),
            }
          })
        } catch {
          return [{ key, id: '', label: 'Характеристики' }]
        }
      }
      const label =
        key === 'category'
          ? catalog.value?.categories.find((item) => item.slug === value)
              ?.name || value
          : key === 'brand'
            ? catalog.value?.category_navigation?.brand?.name ||
              catalog.value?.brand_navigation.find(
                (item) => item.slug === value,
              )?.name ||
              filters.value.brands.find((item) => item.slug === value)?.name ||
              value
            : key === 'in_stock'
              ? 'В наличии'
              : key === 'is_on_sale'
                ? 'Распродажа'
                : key === 'price_from'
                  ? 'Цена от ' + formatMoney(value)
                  : key === 'price_to'
                    ? 'Цена до ' + formatMoney(value)
                    : value
      const prefix =
        key === 'brand' ? 'Бренд: ' : key === 'category' ? 'Категория: ' : ''
      return [{ key, id: '', label: prefix + label }]
    }),
  )

  return chips
}
