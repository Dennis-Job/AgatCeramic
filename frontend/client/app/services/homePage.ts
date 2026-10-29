import type {
  HomePageContent,
  MaterialCategory,
  SiteLink,
  SiteNavigationItem,
} from '~/types/homePage'
import type { ContentBlock, ContentBlockDto } from '~/types/contentPage'
import { searchNavigation } from '~/utils/navigation'

interface LinkDto {
  label: string
  to: string
}
type NavigationItemDto = LinkDto | { label: string; children: LinkDto[] }
interface BannerItemDto {
  id: string
  name: string
  short_description: string
  description: string
  image_url: string | null
  image_alt: string
}

export interface HomePageDto {
  is_indexable?: boolean
  blocks: ContentBlockDto[]
  hero_slides: {
    id: number
    eyebrow: string | null
    title: string
    description: string | null
    image_url: string | null
    image_alt: string | null
    link_label: string | null
    link_url: string | null
  }[]
  marquee: { topics: string[] }
  categories: {
    eyebrow: string
    title: string
    description: string
    items: (BannerItemDto & { link_url?: string | null })[]
  }
  materials: {
    eyebrow: string
    title: string
    description: string
    note?: string | null
    items: (BannerItemDto & {
      link_url?: string | null
      note?: string | null
    })[]
  }
  promo: {
    eyebrow: string
    title: string
    description: string
    link_label: string
    link_url: string
  }
  about: {
    eyebrow: string
    title: string
    description: string
    image_url: string | null
    image_alt: string
    link_label: string
    link_url: string
  }
  guide: {
    eyebrow: string
    title: string
    items: { title: string; description: string }[]
  }
  header: {
    topbar_left: string
    topbar_right: string
    menu_note?: string | null
    navigation: NavigationItemDto[]
    logo_url: string | null
    logo_alt: string | null
  }
  footer: {
    tagline: string
    navigation_links?: LinkDto[]
    social_links?: LinkDto[]
    explore_links: LinkDto[]
    message: {
      eyebrow: string
      text: string
      link_label: string
      link_url: string
    }
    bottom_left: string
    bottom_right: string
    bottom_right_url?: string | null
  }
  seo: {
    title: string
    description: string
    og_title: string
    og_description: string
    og_image_url: string | null
  }
}

export function safeLink(url: string | null | undefined): string | null {
  if (!url || /[\\\s\u0000-\u001f\u007f]/.test(url)) return null
  if (url.startsWith('/') && !url.startsWith('//')) return url
  try {
    const parsed = new URL(url)
    return parsed.protocol === 'https:' && !parsed.username && !parsed.password
      ? url
      : null
  } catch {
    return null
  }
}

function mapLinks(links: LinkDto[]): SiteLink[] {
  return links.flatMap((link) => {
    const to = safeLink(link.to)
    return to && link.label ? [{ label: link.label, to }] : []
  })
}

function mapNavigation(items: NavigationItemDto[]): SiteNavigationItem[] {
  return items.flatMap<SiteNavigationItem>((item) => {
    if ('children' in item) {
      const children = Array.isArray(item.children)
        ? mapLinks(item.children)
        : []
      return item.label && children.length
        ? [{ label: item.label, children }]
        : []
    }
    return mapLinks([item])
  })
}

export function imageUrl(
  url: string | null | undefined,
  apiBase: string,
): string {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  if (!url.startsWith('/') || url.startsWith('//')) return ''
  if (url.startsWith('/images/home/')) return url
  try {
    return new URL(url, apiBase).toString()
  } catch {
    return ''
  }
}

export function mapSiteAppearance(
  dto: Pick<HomePageDto, 'header' | 'footer'>,
  apiBase: string,
): Pick<HomePageContent, 'header' | 'footer'> {
  return {
    header: {
      topbarLeft: dto.header.topbar_left,
      topbarRight: dto.header.topbar_right,
      menuNote:
        dto.header.menu_note === undefined
          ? dto.footer.tagline
          : (dto.header.menu_note ?? ''),
      navigation: mapNavigation(dto.header.navigation),
      logoUrl: imageUrl(dto.header.logo_url, apiBase),
      logoAlt: dto.header.logo_alt || 'AgatCeramic',
    },
    footer: {
      tagline: dto.footer.tagline,
      navigationLinks:
        dto.footer.navigation_links === undefined
          ? searchNavigation(mapNavigation(dto.header.navigation), '')
          : mapLinks(dto.footer.navigation_links),
      socialLinks: mapLinks(dto.footer.social_links ?? []),
      exploreLinks: mapLinks(dto.footer.explore_links),
      message: {
        eyebrow: dto.footer.message.eyebrow,
        text: dto.footer.message.text,
        linkLabel: dto.footer.message.link_label,
        linkUrl: safeLink(dto.footer.message.link_url),
      },
      bottomLeft: dto.footer.bottom_left,
      bottomRight: dto.footer.bottom_right,
      bottomRightUrl: safeLink(dto.footer.bottom_right_url),
    },
  }
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

function mapSlides(
  slides: HomePageDto['hero_slides'],
  apiBase: string,
): HomePageContent['heroSlides'] {
  return slides.map((slide) => ({
    id: String(slide.id),
    eyebrow: slide.eyebrow || '',
    title: slide.title,
    description: slide.description || '',
    image: imageUrl(slide.image_url, apiBase),
    imageAlt: slide.image_alt || slide.title,
    linkLabel: slide.link_label || '',
    linkUrl: safeLink(slide.link_url),
  }))
}

function mapCategories(
  data: HomePageDto['categories'],
  apiBase: string,
): HomePageContent['categories'] {
  return {
    eyebrow: data.eyebrow,
    title: data.title,
    description: data.description,
    items: data.items.map((item) => ({
      ...mapBannerItem(item, apiBase),
      linkUrl: safeBannerLink(item.link_url),
    })),
  }
}

function mapBannerItem(item: BannerItemDto, apiBase: string): MaterialCategory {
  return {
    id: item.id,
    name: item.name,
    shortDescription: item.short_description,
    description: item.description,
    image: imageUrl(item.image_url, apiBase),
    imageAlt: item.image_alt,
  }
}

function safeBannerLink(url: string | null | undefined): string | null {
  const link = safeLink(url)
  if (!link || /[\u0000-\u0020\u007f\\]/.test(link)) return null
  if (link.startsWith('/')) return link
  const parsed = new URL(link)
  return parsed.username || parsed.password ? null : link
}

function mapPromo(data: HomePageDto['promo']): HomePageContent['promo'] {
  return {
    eyebrow: data.eyebrow,
    title: data.title,
    description: data.description,
    linkLabel: data.link_label,
    linkUrl: safeLink(data.link_url),
  }
}

function mapMaterials(
  data: HomePageDto['materials'],
  apiBase: string,
): HomePageContent['materials'] {
  return {
    eyebrow: data.eyebrow,
    title: data.title,
    description: data.description,
    items: data.items.map((item) => ({
      ...mapBannerItem(item, apiBase),
      linkUrl: safeBannerLink(item.link_url),
      note: item.note ?? '',
    })),
  }
}

function mapAbout(
  data: HomePageDto['about'],
  apiBase: string,
): HomePageContent['about'] {
  return {
    ...mapPromo(data),
    image: imageUrl(data.image_url, apiBase),
    imageAlt: data.image_alt,
  }
}

export function mapContentBlock(
  block: ContentBlockDto,
  apiBase: string,
): ContentBlock {
  const common = { id: block.id, enabled: block.enabled }
  switch (block.type) {
    case 'hero':
      return {
        ...common,
        type: 'hero',
        data: {
          slides: mapSlides(block.data.slides ?? [], apiBase),
        },
      }
    case 'categories':
      return {
        ...common,
        type: 'categories',
        data: mapCategories(block.data, apiBase),
      }
    case 'promo':
      return {
        ...common,
        type: 'promo',
        data: mapPromo(block.data),
      }
    case 'about':
      return {
        ...common,
        type: 'about',
        data: mapAbout(block.data, apiBase),
      }
    case 'marquee':
      return { ...common, type: 'marquee', data: block.data }
    case 'materials':
      return {
        ...common,
        type: 'materials',
        data: mapMaterials(block.data, apiBase),
      }
    case 'guide':
      return { ...common, type: 'guide', data: block.data }
    case 'text':
      return { ...common, type: 'text', data: block.data }
    case 'stores':
      return { ...common, type: 'stores', data: block.data }
    case 'catalog':
      return { ...common, type: 'catalog', data: block.data }
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
