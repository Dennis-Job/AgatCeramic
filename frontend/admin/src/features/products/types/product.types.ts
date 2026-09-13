import type { PageRequest } from '../../../services/pagination'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { Brand } from '../../brands/types/brand.types'
import type { Category } from '../../categories/types/category.types'

export type AttributeDraftValue = string | string[]

export type ProductAttributeValue = {
  id: number
  product_id: number
  attribute_id: number
  value: string | number | boolean | string[]
  attribute: Attribute
  created_at: string
  updated_at: string
}

export type ProductAttributePayload = {
  attributes: Array<{ attribute_id: number; value: string | number | boolean | string[] }>
}

export type ProductUnit = 'piece' | 'square_meter' | 'linear_meter' | 'package' | 'kilogram' | 'liter' | 'set'
export type ProductSort = 'sku' | 'name' | 'created_at' | 'updated_at'
export type SortDirection = 'asc' | 'desc'

export type Product = {
  id: number
  category_id: number
  brand_id: number | null
  name: string
  slug: string
  description: string | null
  sku: string
  article_number: string | null
  barcode: string | null
  unit: ProductUnit
  price: string
  old_price: string | null
  stock_quantity: number
  is_active: boolean
  is_on_sale: boolean
  attribute_values?: ProductAttributeValue[]
  primary_image?: { id: number; url: string; alt: string | null } | null
  category: Category
  brand: Brand | null
  created_at: string
  updated_at: string
}

export type ProductPayload = Omit<Product, 'id' | 'sku' | 'category' | 'brand' | 'attribute_values' | 'primary_image' | 'created_at' | 'updated_at'>
export type ProductFilters = { search?: string; category_id?: number; brand_id?: number; is_active?: boolean; is_on_sale?: boolean; has_stock?: boolean; price_from?: string; price_to?: string; sort?: ProductSort; direction?: SortDirection } & PageRequest

export type ProductImport = {
  id: number
  filename: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  created_rows: number
  updated_rows: number
  processed_rows: number
  category_id: number | null
  total_rows: number
  failed_rows: number
  row_errors: { row: number; name: string; messages: string[] }[]
  has_error_file: boolean
  error_message: string | null
  created_at: string
  started_at: string | null
  completed_at: string | null
  operation?: 'catalog' | 'price_status' | 'group'
}

export type ProductImage = { id: number; product_id: number; url: string; mime_type: string; size: number; alt: string | null; is_primary: boolean; sort_order: number; created_at: string; updated_at: string }
export type ProductImageUpdatePayload = { alt?: string | null; is_primary?: boolean; sort_order?: number }

export type ProductImageImportStatus = 'pending' | 'processing' | 'completed' | 'failed'
export type ProductImageImport = {
  id: number
  filename: string
  status: ProductImageImportStatus
  total_folders: number
  processed_folders: number
  created_images: number
  replaced_images: number
  failed_folders: number
  errors: { sku: string; entry: string | null; messages: string[] }[]
  has_error_file: boolean
  error_message: string | null
  created_at?: string
  started_at?: string | null
  completed_at?: string | null
}

export type ProductGroup = {
  id: number
  name: string
  code: string
  axes: Attribute[]
  products: Array<Product & { axis_values?: Array<{ attribute_id: number; value: string | number | boolean | string[]; attribute?: Attribute }> }>
  created_at: string
  updated_at: string
}
export type ProductGroupPayload = { name: string; code: string; axis_attribute_ids: number[]; product_ids: number[] }

export type ProductRelationType = 'related' | 'recommended'
export type ProductRelation = { id: number; product_id: number; related_product_id: number; type: ProductRelationType; sort_order: number; related_product: Product; created_at: string; updated_at: string }
export type ProductRelationPayload = { relations: Array<{ related_product_id: number; type: ProductRelationType; sort_order: number }> }
export type ProductRelationDraft = { related_product_id: string; type: ProductRelationType; sort_order: string }

export type ProductEditorStep = 'main' | 'attributes' | 'images' | 'group' | 'review'

export type ProductEditorStepDefinition = {
  id: ProductEditorStep
  label: string
}
