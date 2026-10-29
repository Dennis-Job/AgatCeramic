import type { CatalogProduct } from '~/types/contentPage'

export interface CartProduct {
  name: string
  slug: string
  sku: string
  unit: CatalogProduct['unit']
  primary_image_url: string | null
}

export interface CartItem {
  id: number
  product_id: number
  quantity: number
  unit_price: string | null
  line_total: string | null
  product: CartProduct | null
}

export interface CartData {
  identifier: string
  items: CartItem[]
}
