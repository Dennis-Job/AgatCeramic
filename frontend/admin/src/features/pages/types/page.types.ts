export type ContentPage = {
  id: number
  title: string
  slug: string
  body: string
  is_published: boolean
  created_at: string
  updated_at: string
}

export type PagePayload = Pick<
  ContentPage,
  'title' | 'slug' | 'body' | 'is_published'
>
