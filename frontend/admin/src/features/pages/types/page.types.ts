import type { PageBlock } from './block.types'
import type { HomePageContent } from '../../homepage/types/homepage.types'

export type PageIntro = {
  image_url: string | null
  image_media_id: number | null
  image_alt: string
}

export type ContentPage = {
  id: number
  title: string
  slug: string
  body: string
  blocks?: PageBlock[]
  seo?: HomePageContent['seo']
  intro?: PageIntro | null
  is_indexable?: boolean
  is_published: boolean
  has_unpublished_changes: boolean
  published_at: string | null
  created_at: string
  updated_at: string
}

export type PagePayload = Pick<
  ContentPage,
  'title' | 'slug' | 'body' | 'blocks' | 'seo' | 'intro' | 'is_indexable'
>
