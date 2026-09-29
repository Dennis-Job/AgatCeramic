export type Banner = {
  id: number
  title: string
  eyebrow: string | null
  description: string | null
  image_url: string | null
  legacy_image_url: string | null
  image_media_id: number | null
  image_alt: string | null
  link_label: string | null
  link_url: string | null
  is_published: boolean
  created_at: string
  updated_at: string
}

export type BannerPayload = {
  title: string
  eyebrow: string
  description: string
  image_url: string
  image_media_id: number | null
  image_alt: string
  link_label: string
  link_url: string
  is_published: boolean
}
