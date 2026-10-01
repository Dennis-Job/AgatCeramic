import type { HomePageContent, SiteLink } from '~/types/homePage'
import type { ContentBlock, ContentBlockDto } from '~/types/contentPage'

interface LinkDto {
  label: string
  to: string
}

export interface HomePageDto {
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
    items: {
      id: string
      name: string
      short_description: string
      description: string
      image_url: string | null
      image_alt: string
    }[]
  }
  materials: {
    eyebrow: string
    title: string
    description: string
    note: string
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
    navigation: LinkDto[]
    logo_url: string | null
    logo_alt: string | null
  }
  footer: {
    tagline: string
    explore_links: LinkDto[]
    message: {
      eyebrow: string
      text: string
      link_label: string
      link_url: string
    }
    bottom_left: string
    bottom_right: string
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
  if (!url) return null
  if (url.startsWith('/') && !url.startsWith('//')) return url
  try {
    const parsed = new URL(url)
    return parsed.protocol === 'https:' ? url : null
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
      navigation: mapLinks(dto.header.navigation),
      logoUrl: imageUrl(dto.header.logo_url, apiBase),
      logoAlt: dto.header.logo_alt || 'AgatCeramic',
    },
    footer: {
      tagline: dto.footer.tagline,
      exploreLinks: mapLinks(dto.footer.explore_links),
      message: {
        eyebrow: dto.footer.message.eyebrow,
        text: dto.footer.message.text,
        linkLabel: dto.footer.message.link_label,
        linkUrl: safeLink(dto.footer.message.link_url),
      },
      bottomLeft: dto.footer.bottom_left,
      bottomRight: dto.footer.bottom_right,
    },
  }
}

function mapHomePage(dto: HomePageDto, apiBase: string): HomePageContent {
  return {
    blocks: dto.blocks.map((block) => mapContentBlock(block, apiBase)),
    heroSlides: mapSlides(dto.hero_slides, apiBase),
    marqueeTopics: dto.marquee.topics,
    categories: mapCategories(dto.categories, apiBase),
    materials: {
      eyebrow: dto.materials.eyebrow,
      title: dto.materials.title,
      description: dto.materials.description,
      note: dto.materials.note,
    },
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
      id: item.id,
      name: item.name,
      shortDescription: item.short_description,
      description: item.description,
      image: imageUrl(item.image_url, apiBase),
      imageAlt: item.image_alt,
    })),
  }
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
      return { ...common, type: 'materials', data: block.data }
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
