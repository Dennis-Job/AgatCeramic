export interface HomeSlide {
  id: string
  eyebrow: string
  title: string
  description: string
  image: string
  imageAlt: string
  linkLabel: string
  linkUrl: string | null
}

export interface MaterialCategory {
  id: string
  name: string
  shortDescription: string
  description: string
  image: string
  imageAlt: string
}

export interface GridBanner extends MaterialCategory {
  linkUrl: string | null
}

interface MenuBanner extends GridBanner {
  note: string
}

interface SectionIntro {
  eyebrow: string
  title: string
  description?: string
}

export interface ContentBlockData {
  hero: { slides: HomeSlide[] }
  marquee: { topics: string[] }
  categories: SectionIntro & { items: GridBanner[] }
  materials: SectionIntro & { items: MenuBanner[] }
  promo: SectionIntro & { linkLabel: string; linkUrl: string | null }
  about: ContentBlockData['promo'] & { image: string; imageAlt: string }
  guide: Pick<SectionIntro, 'eyebrow' | 'title'> & {
    items: { title: string; description: string }[]
  }
  text: { title: string; body: string }
  stores: { title: string }
  catalog: { title: string; description: string }
}

export interface SlideDto {
  id: number
  eyebrow: string | null
  title: string
  description: string | null
  image_url: string | null
  image_alt: string | null
  link_label: string | null
  link_url: string | null
}

export interface BannerItemDto {
  id: string
  name: string
  short_description: string
  description: string
  image_url: string | null
  image_alt: string
}

export interface ContentBlockDataDto extends Pick<
  ContentBlockData,
  'marquee' | 'guide' | 'text' | 'stores' | 'catalog'
> {
  hero: { slider_id: number | null; slides?: SlideDto[] }
  categories: Required<SectionIntro> & {
    items: (BannerItemDto & { link_url?: string | null })[]
  }
  materials: Required<SectionIntro> & {
    note?: string | null
    items: (BannerItemDto & {
      link_url?: string | null
      note?: string | null
    })[]
  }
  promo: Required<SectionIntro> & { link_label: string; link_url: string }
  about: ContentBlockDataDto['promo'] & {
    image_url: string | null
    image_alt: string
  }
}

type Blocks<Data> = {
  [Type in keyof Data]: {
    id: string
    type: Type
    enabled: boolean
    data: Data[Type]
  }
}[keyof Data]

export type ContentBlock = Blocks<ContentBlockData>
export type ContentBlockDto = Blocks<ContentBlockDataDto>

export interface ContentSeoDto {
  title: string
  description: string
  og_title: string
  og_description: string
  og_image_url: string | null
}

export interface ContentSeo {
  title: string
  description: string
  ogTitle: string
  ogDescription: string
  ogImage: string
}

export interface PageIntroImage {
  imageUrl: string
  imageAlt: string
}

export interface PageIntroDto {
  image_url: string | null
  image_media_id?: number | null
  image_alt: string
}

export interface ContentPage {
  intro?: PageIntroImage | null
  isIndexable?: boolean
  title: string
  slug: string
  body: string
  blocks: ContentBlock[]
  seo: ContentSeo
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
  partner_email?: string | null
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
  in_stock: boolean
  size?: string | null
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
