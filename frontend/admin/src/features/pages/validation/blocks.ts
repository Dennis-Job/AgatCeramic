import type { BlockData, BlockType, PageBlock } from '../types/block.types'

export const blockLabels: Record<BlockType, string> = {
  hero: 'Слайдер',
  marquee: 'Бегущая строка',
  categories: 'Материалы',
  materials: 'Блок фактур',
  promo: 'Промо',
  about: 'О проекте',
  guide: 'Советы',
  text: 'Текст',
  stores: 'Магазины',
  catalog: 'Каталог',
}

export function blankBlockData(): BlockData {
  const heading = () => ({ eyebrow: '', title: '', description: '' })
  const cta = () => ({ link_label: '', link_url: '' })
  return {
    hero: { slider_id: null },
    marquee: { topics: [''] },
    categories: {
      ...heading(),
      items: [
        {
          id: '',
          name: '',
          short_description: '',
          description: '',
          image_url: '',
          image_media_id: null,
          image_alt: '',
        },
      ],
    },
    materials: { ...heading(), note: '' },
    promo: { ...heading(), ...cta() },
    about: {
      ...heading(),
      ...cta(),
      image_url: '',
      image_media_id: null,
      image_alt: '',
    },
    guide: { eyebrow: '', title: '', items: [{ title: '', description: '' }] },
    text: { title: '', body: '' },
    stores: { title: '' },
    catalog: { title: '', description: '' },
  }
}

export function createBlock(type: BlockType): PageBlock {
  return {
    id: `${type}-${crypto.randomUUID()}`,
    type,
    enabled: true,
    data: blankBlockData()[type],
  } as PageBlock
}

export function moveBlock(
  blocks: PageBlock[],
  id: string,
  offset: -1 | 1,
): void {
  const index = blocks.findIndex((block) => block.id === id)
  const next = index + offset
  if (index < 0 || next < 0 || next >= blocks.length) return
  ;[blocks[index], blocks[next]] = [blocks[next]!, blocks[index]!]
}
