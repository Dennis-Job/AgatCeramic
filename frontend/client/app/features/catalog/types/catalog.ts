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
  price_range?: { min: string; max: string } | null
  brands: { id: number; name: string; slug: string }[]
  collections: { id: number; name: string; brand_slug: string }[]
  attributes: CatalogAttribute[]
}
export interface StandaloneCatalog extends CatalogResult {
  categories: CatalogNavigationCategory[]
  filters: CatalogFilters
  brand_navigation: CatalogBrand[]
  category_navigation?: CatalogNavigation
}
export interface CatalogNavigationCategory extends CatalogCategory {
  parent_id?: number | null
  product_count?: number
}
export interface CatalogNavigation {
  brand: CatalogBrand | null
  total: number
  categories: CatalogNavigationCategory[]
}
export interface CatalogBrand {
  id: number
  name: string
  slug: string
}
export interface CatalogDraft {
  category: string
  brand: string
  collection: string
  price_from: string
  price_to: string
  in_stock: boolean
  is_on_sale: boolean
  attributes: Record<string, AttributeFilter>
}
export type CatalogQuery = Record<string, string>
