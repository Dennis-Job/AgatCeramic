export type ContentPage = {
  id: number
  title: string
  slug: string
  body: string
  is_published: boolean
  has_unpublished_changes: boolean
  published_at: string | null
  created_at: string
  updated_at: string
}

export type PagePayload = Pick<ContentPage, 'title' | 'slug' | 'body'>
