import type { ContentBlockDto, ContentPage } from '~/types/contentPage'
import type { HomePageDto } from '~/services/homePage'
import type { HomePageContent } from '~/types/homePage'

export interface DraftPreviewDto {
  page: {
    title: string
    slug: string
    body: string
    blocks: ContentBlockDto[]
    seo: HomePageDto['seo']
  }
  appearance: Pick<HomePageDto, 'header' | 'footer'>
}

export interface DraftPreview {
  page: ContentPage
  appearance: Pick<HomePageContent, 'header' | 'footer'>
}
