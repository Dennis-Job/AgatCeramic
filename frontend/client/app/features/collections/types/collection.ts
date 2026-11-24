export interface ProductCollection {
  name: string
  brand_slug: string
  // Older product responses only contain the name and brand slug.
  id?: number
  slug?: string
  headline?: string | null
  description?: string | null
  country_code?: string | null
  image_url?: string | null
  image_alt?: string | null
}

export interface PublicCollection extends ProductCollection {
  id: number
  slug: string
  brand_name: string
}
