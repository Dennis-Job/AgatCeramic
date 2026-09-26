import type { Banner } from '../../banners/types/banner.types'

export type Slider = {
  id: number
  name: string
  slug: string
  is_published: boolean
  banners: Banner[]
  created_at: string
  updated_at: string
}

export type SliderPayload = {
  name: string
  slug: string
  is_published: boolean
  banner_ids: number[]
}
