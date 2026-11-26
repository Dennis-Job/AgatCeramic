import type {
  BannerItemDto,
  ContentBlock,
  ContentBlockData,
  ContentBlockDataDto,
  ContentBlockDto,
  HomeSlide,
  MaterialCategory,
  SlideDto,
} from '~/types/contentPage'
import { imageUrl, safeLink } from '~/utils/siteUrls'

export function mapSlides(slides: SlideDto[], apiBase: string): HomeSlide[] {
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

export function mapCategories(
  data: ContentBlockDataDto['categories'],
  apiBase: string,
): ContentBlockData['categories'] {
  return {
    eyebrow: data.eyebrow,
    title: data.title,
    description: data.description,
    items: data.items.map((item) => ({
      ...mapBannerItem(item, apiBase),
      linkUrl: safeLink(item.link_url),
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

export function mapPromo(
  data: ContentBlockDataDto['promo'],
): ContentBlockData['promo'] {
  return {
    eyebrow: data.eyebrow,
    title: data.title,
    description: data.description,
    linkLabel: data.link_label,
    linkUrl: safeLink(data.link_url),
  }
}

export function mapMaterials(
  data: ContentBlockDataDto['materials'],
  apiBase: string,
): ContentBlockData['materials'] {
  return {
    eyebrow: data.eyebrow,
    title: data.title,
    description: data.description,
    items: data.items.map((item) => ({
      ...mapBannerItem(item, apiBase),
      linkUrl: safeLink(item.link_url),
      note: item.note ?? '',
    })),
  }
}

export function mapAbout(
  data: ContentBlockDataDto['about'],
  apiBase: string,
): ContentBlockData['about'] {
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
