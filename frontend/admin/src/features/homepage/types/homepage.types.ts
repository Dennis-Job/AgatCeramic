import type { SiteAppearance } from '../../appearance/types/appearance.types'

export type SiteLink = { label: string; to: string }
export type HomeImage = {
  image_url: string
  image_media_id: number | null
  image_alt: string
}
export type HomeHeading = {
  eyebrow: string
  title: string
  description: string
}
export type HomeCta = { link_label: string; link_url: string }

export type HomePageContent = {
  page_id: number
  is_published: boolean
  has_unpublished_changes: boolean
  published_at: string | null
  hero_slider_id: number | null
  hero_slides: {
    id: number
    eyebrow: string
    title: string
    description: string
    image_url: string
    image_alt: string
    link_label: string
    link_url: string
  }[]
  header: SiteAppearance['header']
  footer: SiteAppearance['footer']
  marquee: { topics: string[] }
  categories: HomeHeading & {
    items: ({
      id: string
      name: string
      short_description: string
      description: string
    } & HomeImage)[]
  }
  materials: HomeHeading & { note: string }
  promo: HomeHeading & HomeCta
  about: HomeHeading & HomeImage & HomeCta
  guide: Pick<HomeHeading, 'eyebrow' | 'title'> & {
    items: { title: string; description: string }[]
  }
  seo: {
    title: string
    description: string
    og_title: string
    og_description: string
    og_image_url: string
    og_image_media_id: number | null
  }
}

export type EditableSection = Exclude<
  keyof HomePageContent,
  | 'header'
  | 'footer'
  | 'hero_slides'
  | 'page_id'
  | 'is_published'
  | 'has_unpublished_changes'
  | 'published_at'
>
