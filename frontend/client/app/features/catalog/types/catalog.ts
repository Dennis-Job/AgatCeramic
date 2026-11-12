import type { CatalogCategory, CatalogResult } from '~/types/contentPage'

export interface CatalogAttribute {
  id: number
  name: string
  slug: string
  type: string
  unit: string | null
  options: { value: string; label: string }[]
}
export interface AttributeFilter {
  values?: string[]
  min?: string
  max?: string
  text?: string
}
export interface CatalogFilters {
  brands: { id: number; name: string; slug: string }[]
  collections: { id: number; name: string; brand_slug: string }[]
  attributes: CatalogAttribute[]
}
export interface StandaloneCatalog extends CatalogResult {
  categories: (CatalogCategory & { parent_id?: number | null })[]
  filters: CatalogFilters
}
export interface CatalogDraft {
  category: string
  brand: string
  collection: string
  price_from: string
  price_to: string
  in_stock: boolean
  attributes: Record<string, AttributeFilter>
}
export type CatalogQuery = Record<string, string>
