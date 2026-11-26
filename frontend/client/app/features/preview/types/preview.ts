import type {
  ContentBlockDto,
  ContentPage,
  ContentSeoDto,
  PageIntroDto,
} from '~/types/contentPage'
import type { SiteAppearance, SiteAppearanceDto } from '~/types/siteAppearance'

export interface DraftPreviewDto {
  page: {
    title: string
    slug: string
    body: string
    intro?: PageIntroDto | null
    blocks: ContentBlockDto[]
    seo: ContentSeoDto
  }
  appearance: SiteAppearanceDto
}

export interface DraftPreview {
  page: ContentPage
  appearance: SiteAppearance
}
