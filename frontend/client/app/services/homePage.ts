import type {
  ContentBlockDataDto,
  ContentBlockDto,
  ContentSeoDto,
  SlideDto,
} from '~/types/contentPage'
import type { HomePageContent } from '~/types/homePage'
import type { SiteAppearanceDto } from '~/types/siteAppearance'
import { imageUrl } from '~/utils/siteUrls'
import {
  mapAbout,
  mapCategories,
  mapContentBlock,
  mapMaterials,
  mapPromo,
  mapSlides,
} from './contentBlocks'
import { mapSiteAppearance } from './siteAppearance'

interface HomePageDto extends SiteAppearanceDto {
  is_indexable?: boolean
  blocks: ContentBlockDto[]
  hero_slides: SlideDto[]
  marquee: ContentBlockDataDto['marquee']
  categories: ContentBlockDataDto['categories']
  materials: ContentBlockDataDto['materials']
  promo: ContentBlockDataDto['promo']
  about: ContentBlockDataDto['about']
  guide: ContentBlockDataDto['guide']
  seo: ContentSeoDto
}

function mapHomePage(dto: HomePageDto, apiBase: string): HomePageContent {
  return {
    isIndexable: dto.is_indexable !== false,
    blocks: dto.blocks.map((block) => mapContentBlock(block, apiBase)),
    heroSlides: mapSlides(dto.hero_slides, apiBase),
    marqueeTopics: dto.marquee.topics,
    categories: mapCategories(dto.categories, apiBase),
    materials: mapMaterials(dto.materials, apiBase),
    promo: mapPromo(dto.promo),
    about: mapAbout(dto.about, apiBase),
    guide: dto.guide,
    ...mapSiteAppearance(dto, apiBase),
    seo: {
      title: dto.seo.title,
      description: dto.seo.description,
      ogTitle: dto.seo.og_title,
      ogDescription: dto.seo.og_description,
      ogImage: imageUrl(dto.seo.og_image_url, apiBase),
    },
  }
}

export async function fetchHomePage(
  requestApiBase: string,
  publicApiBase: string,
): Promise<HomePageContent> {
  const base = requestApiBase.endsWith('/')
    ? requestApiBase
    : `${requestApiBase}/`
  const publicBase = publicApiBase.endsWith('/')
    ? publicApiBase
    : `${publicApiBase}/`
  const response = await $fetch<{ data: HomePageDto }>(
    new URL('home-page', base).toString(),
  )
  return mapHomePage(response.data, publicBase)
}
