export type Banner = {
  id: number
  title: string
  description: string | null
  image_url: string | null
  link_label: string | null
  link_url: string | null
  is_published: boolean
  created_at: string
  updated_at: string
}

export type BannerPayload = {
  title: string
  description: string
  image_url: string
  link_label: string
  link_url: string
  is_published: boolean
}
