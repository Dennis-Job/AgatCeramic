import type { Brand } from '../../brands/types/brand.types'
import type { Media } from '../../media/types/media.types'
export type CollectionPayload = {
  brand_id: number | null
  name: string
  slug: string
  headline: string | null
  description: string | null
  image_id: number | null
  is_active: boolean
}
export type ProductCollection = Omit<CollectionPayload, 'brand_id'> & {
  id: number
  brand_id: number
  brand: Brand | null
  image: Media | null
  products_count: number
  created_at: string
  updated_at: string
}
