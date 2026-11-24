import type { CatalogProduct } from '~/types/contentPage'
import type { ProductCollection } from '~/features/collections/types/collection'
export interface ProductImage {
  id: number
  url: string
  alt: string
  is_primary: boolean
  sort_order: number
}
export interface ProductAttribute {
  id: number
  slug: string
  name: string
  type: string
  unit: string | null
  value: string | number | boolean | string[] | null
  display_value: string
  group?: { id: number; name: string; sort_order: number } | null
}
export type ProductCard = Omit<CatalogProduct, 'description'>
export interface GroupProduct extends ProductCard {
  sku?: string
  axis_values: Pick<ProductAttribute, 'slug' | 'value' | 'display_value'>[]
}
export interface ProductDetail extends ProductCard {
  sku: string
  article_number: string | null
  description: string | null
  brand:
    | (NonNullable<CatalogProduct['brand']> & {
        logo_url: string | null
        logo_alt: string | null
        page_available?: boolean
      })
    | null
  images: ProductImage[]
  attributes: ProductAttribute[]
  collection: ProductCollection | null
  group: {
    axes: Pick<ProductAttribute, 'id' | 'slug' | 'name' | 'type'>[]
    products: GroupProduct[]
  } | null
  related: ProductCard[]
  recommended: ProductCard[]
}
