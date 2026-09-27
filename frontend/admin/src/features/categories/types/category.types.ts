import type { Media } from '../../media/types/media.types'

export type Category = {
  id: number
  parent_id?: number | null
  name: string
  slug: string
  description: string | null
  image_id: number | null
  image: Media | null
  documents: Media[]
  sku_prefix: string | null
  is_parent: boolean
  is_active: boolean
  sort_order: number
  children?: Category[]
  created_at: string
  updated_at: string
}
export type CategoryPayload = Omit<
  Category,
  | 'id'
  | 'sku_prefix'
  | 'created_at'
  | 'updated_at'
  | 'children'
  | 'image'
  | 'documents'
> & { document_ids: number[] }
export type CategoryAttributeAssignment = {
  id: number
  sort_order: number
  is_required: boolean
}
export type CategoryAttributeGroupAssignment = {
  id: number
  sort_order: number
}
