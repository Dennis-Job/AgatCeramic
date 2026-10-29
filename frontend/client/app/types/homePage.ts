import type { ContentBlock } from './contentPage'

export interface SiteLink {
  label: string
  to: string
}

export type SiteNavigationItem =
  SiteLink | { label: string; children: SiteLink[] }

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
export interface MenuBanner extends MaterialCategory {
  linkUrl: string | null
  note: string
}

export interface SectionIntro {
  eyebrow: string
  title: string
  description?: string
}

export interface HomePageContent {
  isIndexable?: boolean
  blocks: ContentBlock[]
  heroSlides: HomeSlide[]
  marqueeTopics: string[]
  categories: SectionIntro & { items: GridBanner[] }
  materials: SectionIntro & { items: MenuBanner[] }
  promo: SectionIntro & { linkLabel: string; linkUrl: string | null }
  about: SectionIntro & {
    image: string
    imageAlt: string
    linkLabel: string
    linkUrl: string | null
  }
  guide: Pick<SectionIntro, 'eyebrow' | 'title'> & {
    items: { title: string; description: string }[]
  }
  header: {
    topbarLeft: string
    topbarRight: string
    menuNote?: string
    navigation: SiteNavigationItem[]
    logoUrl: string
    logoAlt: string
  }
  footer: {
    tagline: string
    navigationLinks?: SiteLink[]
    socialLinks?: SiteLink[]
    exploreLinks: SiteLink[]
    message: {
      eyebrow: string
      text: string
      linkLabel: string
      linkUrl: string | null
    }
    bottomLeft: string
    bottomRight: string
    bottomRightUrl?: string | null
  }
  seo: {
    title: string
    description: string
    ogTitle: string
    ogDescription: string
    ogImage: string
  }
}
