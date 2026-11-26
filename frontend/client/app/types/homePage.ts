import type { ContentBlock, ContentBlockData, ContentSeo } from './contentPage'
import type { SiteAppearance } from './siteAppearance'

export interface HomePageContent extends SiteAppearance {
  isIndexable?: boolean
  blocks: ContentBlock[]
  heroSlides: ContentBlockData['hero']['slides']
  marqueeTopics: ContentBlockData['marquee']['topics']
  categories: ContentBlockData['categories']
  materials: ContentBlockData['materials']
  promo: ContentBlockData['promo']
  about: ContentBlockData['about']
  guide: ContentBlockData['guide']
  seo: ContentSeo
}
