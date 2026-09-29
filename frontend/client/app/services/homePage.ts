import type { HomePageContent, SiteLink } from '~/types/homePage'

interface LinkDto {
  label: string
  to: string
}

interface HomePageDto {
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

function safeLink(url: string | null | undefined): string | null {
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

function imageUrl(url: string | null | undefined, apiBase: string): string {
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

function mapHomePage(dto: HomePageDto, apiBase: string): HomePageContent {
  return {
    heroSlides: dto.hero_slides.map((slide) => ({
      id: String(slide.id),
      eyebrow: slide.eyebrow || '',
      title: slide.title,
      description: slide.description || '',
      image: imageUrl(slide.image_url, apiBase),
      imageAlt: slide.image_alt || slide.title,
      linkLabel: slide.link_label || '',
      linkUrl: safeLink(slide.link_url),
    })),
    marqueeTopics: dto.marquee.topics,
    categories: {
      eyebrow: dto.categories.eyebrow,
      title: dto.categories.title,
      description: dto.categories.description,
      items: dto.categories.items.map((item) => ({
        id: item.id,
        name: item.name,
        shortDescription: item.short_description,
        description: item.description,
        image: imageUrl(item.image_url, apiBase),
        imageAlt: item.image_alt,
      })),
    },
    materials: {
      eyebrow: dto.materials.eyebrow,
      title: dto.materials.title,
      description: dto.materials.description,
      note: dto.materials.note,
    },
    promo: {
      eyebrow: dto.promo.eyebrow,
      title: dto.promo.title,
      description: dto.promo.description,
      linkLabel: dto.promo.link_label,
      linkUrl: safeLink(dto.promo.link_url),
    },
    about: {
      eyebrow: dto.about.eyebrow,
      title: dto.about.title,
      description: dto.about.description,
      image: imageUrl(dto.about.image_url, apiBase),
      imageAlt: dto.about.image_alt,
      linkLabel: dto.about.link_label,
      linkUrl: safeLink(dto.about.link_url),
    },
    guide: dto.guide,
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
