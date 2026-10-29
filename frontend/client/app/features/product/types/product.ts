import type { CatalogProduct } from '~/types/contentPage'
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
}
export interface ProductCard extends Omit<CatalogProduct, 'description'> {
  in_stock: boolean
}
export interface GroupProduct extends ProductCard {
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
      })
    | null
  images: ProductImage[]
  attributes: ProductAttribute[]
  collection: { name: string; brand_slug: string } | null
  group: {
    axes: Pick<ProductAttribute, 'id' | 'slug' | 'name' | 'type'>[]
    products: GroupProduct[]
  } | null
  related: ProductCard[]
  recommended: ProductCard[]
}
