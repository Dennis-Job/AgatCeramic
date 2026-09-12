export type Brand = {
  id: number
  name: string
  slug: string
  description: string | null
  country_code: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export type BrandPayload = Omit<Brand, 'id' | 'created_at' | 'updated_at'>
