import type { ContentBlock } from './contentPage'

export interface SiteLink {
  label: string
  to: string
}

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

export interface SectionIntro {
  eyebrow: string
  title: string
  description?: string
}

export interface HomePageContent {
  blocks: ContentBlock[]
  heroSlides: HomeSlide[]
  marqueeTopics: string[]
  categories: SectionIntro & { items: MaterialCategory[] }
  materials: SectionIntro & { note: string }
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
    navigation: SiteLink[]
    logoUrl: string
    logoAlt: string
  }
  footer: {
    tagline: string
    exploreLinks: SiteLink[]
    message: {
      eyebrow: string
      text: string
      linkLabel: string
      linkUrl: string | null
    }
    bottomLeft: string
    bottomRight: string
  }
  seo: {
    title: string
    description: string
    ogTitle: string
    ogDescription: string
    ogImage: string
  }
}
