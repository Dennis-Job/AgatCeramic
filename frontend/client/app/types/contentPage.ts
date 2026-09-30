import type { HomePageContent } from './homePage'
import type { HomePageDto } from '~/services/homePage'

type Block<T extends string, D> = {
  id: string
  type: T
  enabled: boolean
  data: D
}

export type ContentBlock =
  | Block<'hero', { slides: HomePageContent['heroSlides'] }>
  | Block<'marquee', { topics: string[] }>
  | Block<'categories', HomePageContent['categories']>
  | Block<'materials', HomePageContent['materials']>
  | Block<'promo', HomePageContent['promo']>
  | Block<'about', HomePageContent['about']>
  | Block<'guide', HomePageContent['guide']>
  | Block<'text', { title: string; body: string }>
  | Block<'stores', { title: string }>
  | Block<'catalog', { title: string; description: string }>

export type ContentBlockDto =
  | Block<
      'hero',
      { slider_id: number | null; slides?: HomePageDto['hero_slides'] }
    >
  | {
      [
        K in
          'marquee' | 'categories' | 'materials' | 'promo' | 'about' | 'guide'
      ]: Block<K, HomePageDto[K]>
    }['marquee' | 'categories' | 'materials' | 'promo' | 'about' | 'guide']
  | Extract<ContentBlock, { type: 'text' | 'stores' | 'catalog' }>

export interface ContentPage {
  title: string
  slug: string
  body: string
  blocks: ContentBlock[]
  seo: HomePageContent['seo']
}

export interface PublicStore {
  id: number
  name: string
  address: string
  phone: string | null
  working_hours: {
    weekday: number
    is_closed: boolean
    opens_at: string | null
    closes_at: string | null
  }[]
}

export interface SellerContacts {
  seller_name: string | null
  address: string | null
  phones: string[]
  email: string | null
}

export interface CatalogCategory {
  id: number
  name: string
  slug: string
  description: string | null
  image_url: string | null
}

export interface CatalogProduct {
  id: number
  name: string
  slug: string
  description: string | null
  price: string | null
  old_price: string | null
  unit:
    | 'piece'
    | 'square_meter'
    | 'linear_meter'
    | 'package'
    | 'kilogram'
    | 'liter'
    | 'set'
  is_on_sale: boolean
  category: Pick<CatalogCategory, 'id' | 'name' | 'slug'>
  brand: { id: number; name: string; slug: string } | null
  image_url: string | null
  image_alt: string
}

export interface CatalogResult {
  data: CatalogProduct[]
  categories: CatalogCategory[]
  meta: { current_page: number; last_page: number; total: number }
}
