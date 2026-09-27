export type Media = {
  id: number
  kind: 'image' | 'document'
  url: string
  thumbnail_url: string | null
  mime_type: string
  size: number
  title: string
  alt: string | null
  width: number | null
  height: number | null
  created_at: string
  updated_at: string
}
